<?php
declare(strict_types=1);
require_once __DIR__.'/GhlGateway.php';

/**
 * Tasks, notes and appointments of one contact inside the conversation. The
 * contact id comes only from a conversation the member is allowed to see, and
 * every record is checked against that contact before it reaches the panel.
 *
 * Appointments are shown only to a member whose calendar module is enabled,
 * even though the published endpoint asks for the contacts scope: the module
 * permission of the account is what decides what that person may see.
 */
final class ContactActivity {
    private const TTL=60;
    private const LIMIT=25;
    private const STATUSES=['confirmed'=>'Confirmada','new'=>'Nueva','showed'=>'Asistió','noshow'=>'No asistió','cancelled'=>'Cancelada','invalid'=>'No válida'];

    public function __construct(private readonly GhlGateway $gateway=new GhlGateway()) {}

    public function get(array $connection,string $contactId,array $membership,bool $refresh=false):array {
        if(!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$contactId))throw new InvalidArgumentException('Invalid contact id');
        $calendars=($membership['capabilities']['viewCalendars']??false)===true;
        $data=$this->read($connection,$contactId,$refresh);
        return [
            'tasks'=>array_map(fn(array $task):array=>$task+['assignedName'=>$this->member($connection,$task['assignedTo'])],$data['tasks']),
            'notes'=>array_map(fn(array $note):array=>$note+['authorName'=>$this->member($connection,$note['userId'])],$data['notes']),
            'appointments'=>$calendars?array_map(fn(array $event):array=>$event+['assignedName'=>$this->member($connection,$event['assignedUserId'])],$data['appointments']):[],
            'appointmentsVisible'=>$calendars,
            'canEdit'=>($membership['capabilities']['editContacts']??false)===true,
            'partial'=>['tasks'=>count($data['tasks'])>=self::LIMIT,'notes'=>count($data['notes'])>=self::LIMIT,'appointments'=>count($data['appointments'])>=self::LIMIT],
        ];
    }

    public static function forget(int $connectionId,string $contactId):void {
        query('DELETE FROM contact_activity_cache WHERE connection_id=? AND contact_id=?',[$connectionId,$contactId]);
    }

    private function read(array $connection,string $contactId,bool $refresh):array {
        $cached=query('SELECT payload,UNIX_TIMESTAMP(fetched_at) AS fetched FROM contact_activity_cache WHERE connection_id=? AND contact_id=?',[$connection['id'],$contactId])->fetch();
        if(!$refresh&&$cached&&time()-(int)$cached['fetched']<self::TTL) {
            $value=json_decode($cached['payload'],true);
            if(is_array($value)&&isset($value['tasks'],$value['notes'],$value['appointments']))return $value;
        }
        // The three reads share one gateway slot: the budget spaces slots by a
        // second and the panel needs the whole block on the first view.
        $data=$this->gateway->execute($connection,function(GhlClient $client)use($contactId):array {
            return [
                'tasks'=>$this->tasks($client->contactTasks($contactId),$contactId),
                'notes'=>$this->notes($client->contactNotes($contactId),$contactId),
                'appointments'=>$this->appointments($client->contactAppointments($contactId),$contactId),
            ];
        },false);
        query('INSERT INTO contact_activity_cache(connection_id,contact_id,payload) VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),fetched_at=CURRENT_TIMESTAMP',[$connection['id'],$contactId,json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
        return $data;
    }

    private function tasks(array $response,string $contactId):array {
        $items=[];
        foreach(self::entries($response['tasks']??null) as $task) {
            $id=self::identifier($task['id']??null);
            if($id===null||!self::belongs($task,$contactId))continue;
            $items[]=[
                'id'=>$id,
                'title'=>self::plain($task['title']??null,190)??'Tarea',
                'body'=>self::plain($task['body']??null,2000),
                'dueDate'=>self::text($task['dueDate']??null,80),
                'completed'=>($task['completed']??false)===true,
                'assignedTo'=>self::identifier($task['assignedTo']??null),
            ];
        }
        // Pending first, then the nearest due date.
        usort($items,fn(array $a,array $b):int=>[$a['completed'],(string)$a['dueDate']]<=>[$b['completed'],(string)$b['dueDate']]);
        return $items;
    }

    private function notes(array $response,string $contactId):array {
        $items=[];
        foreach(self::entries($response['notes']??null) as $note) {
            $id=self::identifier($note['id']??null);
            $body=self::plain($note['body']??null,5000);
            if($id===null||$body===null||!self::belongs($note,$contactId))continue;
            $items[]=['id'=>$id,'body'=>$body,'dateAdded'=>self::text($note['dateAdded']??null,80),'userId'=>self::identifier($note['userId']??null)];
        }
        usort($items,fn(array $a,array $b):int=>strcmp((string)$b['dateAdded'],(string)$a['dateAdded']));
        return $items;
    }

    private function appointments(array $response,string $contactId):array {
        $items=[];
        foreach(self::entries($response['events']??null) as $event) {
            $id=self::identifier($event['id']??null);
            if($id===null||!self::belongs($event,$contactId))continue;
            $status=$event['appointmentStatus']??$event['status']??null;
            $status=is_string($status)&&isset(self::STATUSES[strtolower($status)])?strtolower($status):null;
            $items[]=[
                'id'=>$id,
                'title'=>self::plain($event['title']??null,190)??'Cita',
                'startTime'=>self::text($event['startTime']??null,80),
                'endTime'=>self::text($event['endTime']??null,80),
                'status'=>$status,
                'statusLabel'=>$status!==null?self::STATUSES[$status]:null,
                'address'=>self::text($event['address']??null,300),
                'assignedUserId'=>self::identifier($event['assignedUserId']??null),
            ];
        }
        usort($items,fn(array $a,array $b):int=>strcmp((string)$b['startTime'],(string)$a['startTime']));
        return $items;
    }

    /** A record without a contact is kept; one naming another contact is not. */
    private static function belongs(array $record,string $contactId):bool {
        return !isset($record['contactId'])||$record['contactId']===$contactId;
    }

    private static function entries(mixed $value):array {
        if(!is_array($value)||!array_is_list($value))throw new RuntimeException('Invalid activity response');
        return array_filter(array_slice($value,0,self::LIMIT),'is_array');
    }

    private function member(array $connection,?string $externalId):?string {
        if($externalId===null)return null;
        $name=query('SELECT display_name FROM external_users WHERE connection_id=? AND external_user_id=? ORDER BY active DESC LIMIT 1',[$connection['id'],$externalId])->fetchColumn();
        return is_string($name)?$name:null;
    }

    private static function identifier(mixed $value):?string {
        return is_string($value)&&preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$value)?$value:null;
    }
    /**
     * Tasks and notes are stored as rich text upstream. The panel shows reading
     * text, never markup: tags become line breaks or disappear, entities are
     * decoded once, and the result is measured after cleaning, not before.
     */
    private static function plain(mixed $value,int $max):?string {
        if(!is_scalar($value))return null;
        $text=(string)$value;
        if(str_contains($text,'<')) {
            $text=preg_replace('~<\s*(br|/p|/div|/li|/tr|/h[1-6])\b[^>]*>~i',"\n",$text)??$text;
            $text=strip_tags($text);
        }
        $text=html_entity_decode($text,ENT_QUOTES|ENT_HTML5,'UTF-8');
        $text=preg_replace('/[ \t\x{00a0}]+/u',' ',$text)??$text;
        $text=preg_replace('/\n{3,}/',"\n\n",$text)??$text;
        $text=trim($text);
        if($text==='')return null;
        return mb_strlen($text)<=$max?$text:mb_substr($text,0,$max);
    }

    private static function text(mixed $value,int $max):?string {
        if(!is_scalar($value))return null;
        $value=trim((string)$value);
        return $value!==''&&mb_strlen($value)<=$max?$value:null;
    }
}
