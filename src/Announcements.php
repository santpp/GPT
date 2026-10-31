<?php
declare(strict_types=1);

final class AnnouncementProblem extends RuntimeException {
    public function __construct(public readonly string $reason,string $message){parent::__construct($message);}
}

/**
 * Las novedades que escribe el administrador. Cada persona del equipo ve cada
 * una una sola vez, al entrar. Nada de esto sale de la empresa a la que
 * pertenece: se filtra siempre por empresa.
 */
final class Announcements {
    private const KEEP=5;

    /** Lo que esta persona aún no ha visto, lo más reciente primero. */
    public static function pending(array $actor):array {
        try {
            $rows=query('SELECT a.id,a.title,a.body,a.link_label,a.link_url,UNIX_TIMESTAMP(a.created_at) AS created FROM announcements a
                WHERE a.tenant_id=? AND a.active=TRUE AND (a.expires_at IS NULL OR a.expires_at>?)
                AND NOT EXISTS(SELECT 1 FROM announcement_reads r WHERE r.announcement_id=a.id AND r.identity_type=? AND r.actor_id=?)
                ORDER BY a.id DESC LIMIT '.self::KEEP,[$actor['tenant_id'],time(),$actor['identity_type'],$actor['id']])->fetchAll();
        } catch(PDOException $e) {return [];}
        return array_map(fn($r)=>['id'=>(int)$r['id'],'title'=>$r['title'],'body'=>$r['body'],'linkLabel'=>$r['link_label'],'linkUrl'=>$r['link_url'],'at'=>gmdate('c',(int)$r['created'])],$rows);
    }

    public static function markRead(array $actor,int $id):void {
        $own=query('SELECT 1 FROM announcements WHERE id=? AND tenant_id=?',[$id,$actor['tenant_id']])->fetchColumn();
        if(!$own)throw new AnnouncementProblem('unknown','Esa novedad no existe.');
        query('INSERT IGNORE INTO announcement_reads(announcement_id,identity_type,actor_id) VALUES(?,?,?)',[$id,$actor['identity_type'],$actor['id']]);
    }

    /** Para el administrador: lo publicado y a cuántos les ha llegado. */
    public static function list(int $tenant):array {
        $rows=query('SELECT a.id,a.title,a.body,a.link_label,a.link_url,a.active,a.expires_at,UNIX_TIMESTAMP(a.created_at) AS created,
                (SELECT COUNT(*) FROM announcement_reads r WHERE r.announcement_id=a.id) AS seen FROM announcements a WHERE a.tenant_id=? ORDER BY a.id DESC LIMIT 30',[$tenant])->fetchAll();
        return array_map(fn($r)=>['id'=>(int)$r['id'],'title'=>$r['title'],'body'=>$r['body'],'linkLabel'=>$r['link_label'],'linkUrl'=>$r['link_url'],'active'=>(bool)$r['active'],
            'expiresAt'=>$r['expires_at']?gmdate('c',(int)$r['expires_at']):null,'at'=>gmdate('c',(int)$r['created']),'seen'=>(int)$r['seen']],$rows);
    }

    public static function create(int $tenant,array $input):int {
        $title=self::text($input['title']??null,120);
        $body=self::text($input['body']??null,2000,true);
        if($title===null)throw new AnnouncementProblem('title','Escribe un título de hasta 120 caracteres.');
        if($body===null)throw new AnnouncementProblem('body','Escribe el texto de la novedad (hasta 2.000 caracteres).');
        $label=self::text($input['linkLabel']??null,40);$url=is_string($input['linkUrl']??null)?trim($input['linkUrl']):'';
        if(($label===null)!==($url===''))throw new AnnouncementProblem('link','Para añadir un botón escribe su texto y su dirección, o deja los dos vacíos.');
        if($url!==''&&!preg_match('~^(https://[^\s<>"]{3,280}|/[A-Za-z0-9/_-]{0,100})$~D',$url))throw new AnnouncementProblem('link_url','La dirección debe empezar por https:// o ser una pantalla de Ágora, como /tablero.');
        $days=$input['days']??null;
        $expires=is_numeric($days)&&(int)$days>0&&(int)$days<=365?time()+(int)$days*86400:null;
        query('INSERT INTO announcements(tenant_id,title,body,link_label,link_url,expires_at) VALUES(?,?,?,?,?,?)',[$tenant,$title,$body,$label,$url===''?null:$url,$expires]);
        return (int)db()->lastInsertId();
    }

    /** Retirarla la quita a quien aún no la ha visto. */
    public static function remove(int $tenant,int $id):bool {
        return query('DELETE FROM announcements WHERE id=? AND tenant_id=?',[$id,$tenant])->rowCount()>0;
    }

    private static function text(mixed $value,int $max,bool $multiline=false):?string {
        if(!is_string($value))return null;
        $value=str_replace("\r\n","\n",trim($value));
        if(!$multiline)$value=preg_replace('/\s+/u',' ',$value);
        else $value=preg_replace("/\n{3,}/","\n\n",$value);
        $value=preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/','',$value);
        return $value===''||mb_strlen($value)>$max?null:$value;
    }
}
