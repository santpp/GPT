<?php
declare(strict_types=1);

/**
 * Copia de seguridad diaria: la base de datos sin las cachés (se rehacen solas
 * leyendo la cuenta) y, si hay frase de cifrado, también las claves de `var/` y
 * la configuración privada, sin las que los tokens guardados no se pueden abrir.
 *
 * Con frase, el archivo va cifrado (AES-256-GCM, clave derivada con PBKDF2) y se
 * puede sacar del servidor sin miedo. Sin frase, sólo lleva la base de datos y
 * lo dice: las claves nunca se copian sin cifrar.
 */
final class Backup {
    public const MAGIC='AGORABK1';
    private const ITERATIONS=200000;
    /** Lo que se rehace solo o no merece la pena guardar. */
    private const SKIP=['contact_activity_cache','contact_field_cache','contact_profile_cache','conversation_index','conversation_message_cache',
        'directory_cache','opportunity_cache','pipeline_cache','ghl_inbox_snapshots','login_attempts','external_login_challenges','webhook_events'];

    public static function run(string $dir,?string $passphrase,array $files,int $keep=14):array {
        if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('backup_dir');
        @chmod($dir,0700);
        $encrypted=$passphrase!==null&&$passphrase!=='';
        if($encrypted&&strlen($passphrase)<12)throw new InvalidArgumentException('passphrase_short');
        $stamp=gmdate('Ymd-His');
        $zipPath=$dir.'/agora-'.$stamp.'.zip';
        $zip=new ZipArchive();
        if($zip->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('backup_zip');
        [$sql,$tables]=self::dump();
        $zip->addFromString('database.sql',$sql);
        $copied=[];
        if($encrypted) foreach($files as $name=>$path)
            if(is_string($path)&&$path!==''&&is_file($path)&&is_readable($path)){$zip->addFile($path,'private/'.$name);$copied[]=$name;}
        $zip->addFromString('LEEME.txt',self::readme($encrypted,$copied));
        $zip->close();
        $final=$zipPath;
        if($encrypted) {
            $final=$dir.'/agora-'.$stamp.'.agora-backup';
            file_put_contents($final,self::seal((string)file_get_contents($zipPath),$passphrase));
            unlink($zipPath);
        }
        @chmod($final,0600);
        self::prune($dir,$keep);
        return ['file'=>basename($final),'bytes'=>filesize($final),'tables'=>$tables,'encrypted'=>$encrypted,'keys'=>$copied];
    }

    /** Las sentencias SQL que rehacen las tablas con datos propios. */
    public static function dump():array {
        $out="-- Copia de Ágora ".gmdate('c')."\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n";
        $count=0;
        foreach(query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM) as [$table]) {
            if(in_array($table,self::SKIP,true)||!preg_match('/^[A-Za-z0-9_]+$/D',$table))continue;
            $count++;
            $create=query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
            $out.="\nDROP TABLE IF EXISTS `$table`;\n$create;\n";
            $rows=query('SELECT * FROM `'.$table.'`');
            $batch=[];$columns=[];
            while($row=$rows->fetch(PDO::FETCH_ASSOC)) {
                $columns=array_keys($row);
                $batch[]='('.implode(',',array_map(fn($v)=>$v===null?'NULL':db()->quote((string)$v),$row)).')';
                if(count($batch)===200){$out.=self::insert($columns,$table,$batch);$batch=[];}
            }
            if($batch)$out.=self::insert($columns,$table,$batch);
        }
        return [$out."\nSET FOREIGN_KEY_CHECKS=1;\n",$count];
    }
    private static function insert(array $columns,string $table,array $rows):string {
        $cols=implode(',',array_map(fn($c)=>'`'.$c.'`',$columns));
        return "INSERT INTO `$table` ($cols) VALUES\n".implode(",\n",$rows).";\n";
    }

    /** Cifra con una frase: cabecera, sal, vector, etiqueta y datos. */
    public static function seal(string $plain,string $passphrase):string {
        $salt=random_bytes(16);$iv=random_bytes(12);
        $key=hash_pbkdf2('sha256',$passphrase,$salt,self::ITERATIONS,32,true);
        $cipher=openssl_encrypt($plain,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,self::MAGIC);
        if($cipher===false)throw new RuntimeException('backup_encrypt');
        return self::MAGIC.$salt.$iv.$tag.$cipher;
    }
    public static function open(string $sealed,string $passphrase):string {
        if(strlen($sealed)<52||!str_starts_with($sealed,self::MAGIC))throw new InvalidArgumentException('not_a_backup');
        $salt=substr($sealed,8,16);$iv=substr($sealed,24,12);$tag=substr($sealed,36,16);
        $key=hash_pbkdf2('sha256',$passphrase,$salt,self::ITERATIONS,32,true);
        $plain=openssl_decrypt(substr($sealed,52),'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,self::MAGIC);
        if($plain===false)throw new InvalidArgumentException('wrong_passphrase');
        return $plain;
    }

    private static function prune(string $dir,int $keep):void {
        $all=array_merge(glob($dir.'/agora-*.agora-backup')?:[],glob($dir.'/agora-*.zip')?:[]);
        rsort($all);
        foreach(array_slice($all,$keep) as $old)@unlink($old);
    }

    private static function readme(bool $encrypted,array $keys):string {
        return "Copia de seguridad de Ágora (".gmdate('c').")\n\n"
            ."database.sql  Base de datos sin cachés. Restaurar: mysql -u USUARIO -p BASE < database.sql\n"
            .($encrypted?"private/      Claves y configuración privada: ".implode(', ',$keys).". Van en agora-private/var y agora-private/config.\n"
                :"Sin frase de cifrado, esta copia NO incluye las claves de var/. Guárdalas aparte: sin integration.key no se pueden abrir los tokens.\n")
            ."\nTras restaurar, la revisión de cada minuto vuelve a rellenar las cachés sola.\n";
    }
}
