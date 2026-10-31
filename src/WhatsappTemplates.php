<?php
declare(strict_types=1);
require_once __DIR__.'/GhlGateway.php';

final class TemplateProblem extends RuntimeException {
    public function __construct(public readonly string $reason,public readonly int $httpStatus,string $message){parent::__construct($message);}
}

/**
 * Plantillas de WhatsApp aprobadas por Meta, para escribir pasadas las 24 horas.
 *
 * El listado es el publicado (GET /locations/{id}/templates, type=whatsapp,
 * originId = el número de WhatsApp de la subcuenta). La forma de cada plantilla
 * de WhatsApp no está descrita en la referencia, así que se lee con cuidado: se
 * aceptan los nombres de campo de Meta y los de la cuenta, y lo que no se
 * entiende se deja fuera en vez de adivinarse.
 *
 * El envío reproduce lo que hace la propia cuenta al mandar una plantilla:
 * whatsapp.type=template con nombre, idioma, categoría, los valores de las
 * variables, el número que envía y el del contacto. Los campos de la ficha
 * ({{contact.name}}…) los rellena la cuenta, no Ágora.
 */
final class WhatsappTemplates {
    private const TTL=600;
    private const PAGES=5;
    private const APPROVED=['approved','active','enabled'];
    /** Nombres legibles de los campos de la ficha más habituales. */
    public const FIELDS=[
        'contact.name'=>'nombre del contacto','contact.first_name'=>'nombre','contact.last_name'=>'apellidos','contact.full_name'=>'nombre completo',
        'contact.phone'=>'teléfono','contact.email'=>'correo','contact.company_name'=>'empresa','user.name'=>'tu nombre','user.first_name'=>'tu nombre',
        'location.name'=>'nombre de la empresa',
    ];

    public function __construct(private readonly GhlGateway $gateway=new GhlGateway()){}

    public static function numberId(mixed $value):?string {
        if($value===null||$value==='')return null;
        if(!is_string($value)||!preg_match('/^[0-9]{6,30}$/D',trim($value)))throw new TemplateProblem('invalid_number',422,'El identificador del número son sólo cifras (entre 6 y 30).');
        return trim($value);
    }

    /** Las plantillas listas para usar, de la caché si es reciente. */
    public function list(array $connection,bool $fresh=false):array {
        $number=$connection['whatsapp_number_id']??null;
        if(!is_string($number)||$number==='')throw new TemplateProblem('number_missing',409,'Falta el identificador del número de WhatsApp de esta cuenta. El administrador lo añade en Conexiones.');
        $key='wa-templates:'.$number;
        $row=query('SELECT payload,UNIX_TIMESTAMP(fetched_at) AS fetched FROM directory_cache WHERE connection_id=? AND cache_key=?',[$connection['id'],$key])->fetch();
        if($row&&!$fresh&&(int)$row['fetched']>time()-self::TTL)return json_decode($row['payload'],true);
        $raw=[];
        for($page=0;$page<self::PAGES;$page++) {
            $data=$this->gateway->execute($connection,fn(GhlClient $client)=>$client->whatsappTemplates($connection['location_id'],$number,$page*100),false);
            $items=self::items($data);
            $raw=array_merge($raw,$items);
            $total=$data['totalCount']??$data['total']??null;
            if(count($items)<100||(is_numeric($total)&&count($raw)>=(int)$total))break;
        }
        $templates=[];
        foreach($raw as $item){$t=self::normalize($item);if($t)$templates[]=$t;}
        usort($templates,fn($a,$b)=>strcmp($a['name'],$b['name']));
        query('INSERT INTO directory_cache(connection_id,cache_key,payload) VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),fetched_at=CURRENT_TIMESTAMP',[$connection['id'],$key,json_encode($templates,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
        return $templates;
    }

    private static function items(array $data):array {
        foreach([$data['templates']??null,$data['data']??null,$data['items']??null,array_is_list($data)?$data:null] as $candidate)
            if(is_array($candidate)&&array_is_list($candidate))return array_values(array_filter($candidate,'is_array'));
        return [];
    }

    /** Una plantilla de la cuenta, en una forma que Ágora entiende, o null. */
    public static function normalize(array $t):?array {
        $inner=is_array($t['template']??null)?$t['template']:[];
        $name=self::str($t['name']??$inner['name']??$t['templateName']??null,512);
        if($name===null||!preg_match('/^[a-z0-9_]{1,512}$/D',$name))return null;
        $status=self::str($t['status']??$inner['status']??null,40);
        if($status!==null&&!in_array(strtolower($status),self::APPROVED,true))return null;
        $language=$t['language']??$inner['language']??$t['lang']??$inner['lang']??null;
        if(is_array($language))$language=$language['code']??null;
        $language=self::str($language,15);
        if($language===null||!preg_match('/^[a-z]{2,3}(_[A-Za-z]{2,4})?$/D',$language))return null;
        $category=self::str($t['category']??$inner['category']??null,40);
        $components=$t['components']??$inner['components']??null;
        $components=is_array($components)&&array_is_list($components)?$components:[];
        $part=function(string $type)use($components):?array{foreach($components as $c)if(is_array($c)&&strtoupper((string)($c['type']??''))===$type)return $c;return null;};
        $body=self::str($part('BODY')['text']??$t['body']??$inner['body']??$t['message']??$t['content']??$inner['text']??null,1024);
        if($body===null)return null;
        $header=$part('HEADER');
        $headerFormat=strtoupper((string)($header['format']??($header?'TEXT':'')));
        $headerText=$headerFormat==='TEXT'?self::str($header['text']??null,60):null;
        $footer=self::str($part('FOOTER')['text']??$t['footer']??null,60);
        $buttons=[];$dynamicButton=false;
        foreach((array)($part('BUTTONS')['buttons']??[]) as $button) if(is_array($button)) {
            $label=self::str($button['text']??null,25);if($label!==null)$buttons[]=$label;
            if(is_string($button['url']??null)&&str_contains($button['url'],'{{'))$dynamicButton=true;
        }
        // Las variables ya asociadas a un campo de la ficha en la cuenta.
        $mapped=$t['placeholders']??$inner['placeholders']??$t['variables']??null;
        $mapped=is_array($mapped)?$mapped:[];
        $bodyVars=self::variables($body,is_array($mapped['body']??null)?$mapped['body']:(array_is_list($mapped)?$mapped:[]));
        $headerVars=$headerText!==null?self::variables($headerText,is_array($mapped['header']??null)?$mapped['header']:[]):[];
        // Lo que todavía no se sabe mandar bien se enseña, pero no se ofrece enviar.
        $unsupported=in_array($headerFormat,['IMAGE','VIDEO','DOCUMENT','LOCATION'],true)?'media_header':($dynamicButton?'dynamic_button':null);
        $id=self::str($t['id']??$t['_id']??$inner['id']??null,80)??$name.':'.$language;
        return ['id'=>$id,'name'=>$name,'language'=>$language,'category'=>$category,'header'=>$headerText,'body'=>$body,'footer'=>$footer,'buttons'=>$buttons,
            'variables'=>['header'=>$headerVars,'body'=>$bodyVars],'unsupported'=>$unsupported];
    }

    /**
     * Las variables de un texto, en orden. Un campo de la ficha ({{contact.name}})
     * lo rellena la cuenta; una variable numerada ({{1}}) se rellena con lo que la
     * cuenta tenga asociado o, si nada, con lo que escriba quien envía.
     */
    private static function variables(string $text,array $mapped):array {
        preg_match_all('/\{\{\s*([A-Za-z0-9_.]{1,60})\s*\}\}/',$text,$m);
        $out=[];$seen=[];
        foreach($m[1] as $token) {
            if(isset($seen[$token]))continue;$seen[$token]=true;
            if(ctype_digit($token)) {
                $preset=$mapped[(int)$token-1]??null;
                $preset=is_string($preset)&&trim($preset)!==''?trim($preset):null;
                $field=$preset!==null&&preg_match('/^\{\{\s*([A-Za-z0-9_.]+)\s*\}\}$/D',$preset,$f)?$f[1]:null;
                $out[]=['token'=>$token,'fixed'=>$preset,'label'=>$field?(self::FIELDS[$field]??$field):($preset!==null?$preset:'Variable '.$token)];
            } else $out[]=['token'=>$token,'fixed'=>'{{'.$token.'}}','label'=>self::FIELDS[$token]??$token];
        }
        return $out;
    }

    /**
     * Lo que se manda a la cuenta para enviar una plantilla. Comprueba la
     * plantilla contra la lista del servidor y rellena las variables.
     */
    public static function payload(array $template,array $values,string $contact,string $from,string $to):array {
        if($template['unsupported'])throw new TemplateProblem('template_unsupported',422,'Esta plantilla lleva una imagen o un enlace variable. Por ahora envíala desde la cuenta.');
        $fill=function(string $part)use($template,$values):array{
            $list=[];
            foreach($template['variables'][$part] as $v) {
                if($v['fixed']!==null){$list[$v['token']]=$v['fixed'];continue;}
                $given=$values[$part][$v['token']]??null;
                // Meta no admite saltos de línea, tabuladores ni más de cuatro espacios seguidos en una variable.
                $given=is_string($given)?trim(preg_replace('/\s+/u',' ',$given)):'';
                if($given===''||mb_strlen($given)>200)throw new TemplateProblem('template_values',422,'Rellena «'.$v['label'].'» (hasta 200 caracteres).');
                $list[$v['token']]=$given;
            }
            return $list;
        };
        $body=$fill('body');$header=$fill('header');
        $render=fn(string $text,array $list)=>preg_replace_callback('/\{\{\s*([A-Za-z0-9_.]{1,60})\s*\}\}/',fn($m)=>ctype_digit($m[1])&&isset($list[$m[1]])?$list[$m[1]]:$m[0],$text);
        $message=($template['header']!==null?$render($template['header'],$header)."\n\n":'').$render($template['body'],$body).($template['footer']?"\n\n".$template['footer']:'');
        return [
            'type'=>'WhatsApp','contactId'=>$contact,'message'=>$message,
            'whatsapp'=>[
                'type'=>'template',
                'template'=>array_filter(['name'=>$template['name'],'lang'=>$template['language'],'category'=>$template['category']],fn($v)=>$v!==null),
                'placeholders'=>['header'=>array_values($header),'body'=>array_values($body),'buttons'=>[]],
                'fromNumberId'=>$from,'toNumber'=>$to,
            ],
        ];
    }

    /** El teléfono del contacto en formato internacional, de la ficha que guarda el servidor. */
    public static function phone(?string $raw):?string {
        if(!is_string($raw))return null;
        $digits=preg_replace('/[^0-9+]/','',$raw);
        if(!preg_match('/^\+?[0-9]{8,15}$/D',$digits))return null;
        return str_starts_with($digits,'+')?$digits:'+'.$digits;
    }

    private static function str(mixed $v,int $max):?string {
        if(!is_string($v))return null;$v=trim($v);
        return $v===''||mb_strlen($v)>$max?null:$v;
    }
}
