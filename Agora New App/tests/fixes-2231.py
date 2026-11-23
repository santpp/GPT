#!/usr/bin/env python3
# © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt.
"""Agora New App 2.23.1 · Base creada en latin1 (como la crea CyberPanel): el diagnóstico salía en rojo con
«6 sin utf8mb4» en mi.agoradigital.app. La migración 016 pone la base en utf8mb4 y convierte columna por columna las tablas
(respeta las columnas ASCII del motor, los valores por defecto y los datos). Usa una base aparte (agora_lat1) y la borra al final.
Requiere MariaDB local en /tmp/mdb y servidor/config/config.php local. Uso: python3 tests/fixes-2231.py"""
import os,subprocess,shutil,tempfile
AQUI=os.path.dirname(os.path.abspath(__file__));SRV=os.path.join(os.path.dirname(AQUI),'servidor');n=0
def ok(c,m):
    global n
    if not c:print('FALLA:',m);raise SystemExit(1)
    n+=1;print('PASS',m)
def sql(q,base='agora_lat1'):return subprocess.run(['mysql','--socket=/tmp/mdb/run/mysql.sock','--default-character-set=utf8mb4','-uroot','-N','-r',base,'-e',q],capture_output=True,text=True).stdout.strip()
tmp=tempfile.mkdtemp();cfg=os.path.join(tmp,'cfg.php');env=dict(os.environ,AGORA_CONFIG=cfg)
def php(*a):return subprocess.run(['php',*a],cwd=SRV,env=env,capture_output=True,text=True)
mig=os.path.join(SRV,'app/database/migrations/016_utf8mb4.sql');apartada=os.path.join(tmp,'016.sql')
try:
    sql("DROP DATABASE IF EXISTS agora_lat1;CREATE DATABASE agora_lat1 CHARACTER SET latin1 COLLATE latin1_swedish_ci",base='mysql')
    php('-r','$c=require "config/config.php";$c["db"]["name"]="agora_lat1";file_put_contents($argv[1],"<?php return ".var_export($c,true).";");',cfg)
    shutil.move(mig,apartada)
    r=php('bin/migrate.php');ok('"ok":true' in r.stdout,'se instala con la versión anterior en una base latin1 (como CyberPanel)')
    ok('sin utf8mb4' in php('bin/diagnostico.php').stdout,'el diagnóstico ve el problema (tablas sin utf8mb4)')
    sql("CREATE TABLE mot_t (id BIGINT AUTO_INCREMENT PRIMARY KEY, clave VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, nombre VARCHAR(120) NOT NULL DEFAULT 'sin nombre', nota TEXT NULL, estado ENUM('a','b') NOT NULL DEFAULT 'a', KEY(clave)) ENGINE=InnoDB;INSERT INTO mot_t(clave,nombre) VALUES('k1','José Ñandú');INSERT INTO agora_rate_limits(bucket,hits,expires_at) VALUES('prueba',1,UTC_TIMESTAMP())")
    shutil.move(apartada,mig)
    r=php('bin/migrate.php');ok('016_utf8mb4.sql' in r.stdout,'al actualizar se aplica la 016')
    d=php('bin/diagnostico.php').stdout
    ok('Tablas en utf8mb4' in d and 'todas' in d.split('Tablas en utf8mb4')[1].split('\n')[0],'el diagnóstico queda en verde: todas las tablas en utf8mb4')
    ok(sql("SELECT DEFAULT_CHARACTER_SET_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='agora_lat1'")=='utf8mb4','la base queda en utf8mb4 (lo que se cree después, también el motor, nace así)')
    cols=dict(tuple(l.split('\t')[:2]) for l in sql("SELECT COLUMN_NAME,CHARACTER_SET_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='agora_lat1' AND TABLE_NAME='mot_t' AND CHARACTER_SET_NAME IS NOT NULL").splitlines())
    ok(cols=={'clave':'ascii','nombre':'utf8mb4','nota':'utf8mb4','estado':'utf8mb4'},f'columna por columna, y las ASCII del motor se respetan {cols}')
    ok(sql("SELECT CONCAT(nombre,'|',estado,'|',COLUMN_DEFAULT) FROM mot_t, information_schema.COLUMNS WHERE TABLE_SCHEMA='agora_lat1' AND TABLE_NAME='mot_t' AND COLUMN_NAME='nombre'")=="José Ñandú|a|'sin nombre'",'los datos y los valores por defecto se conservan')
    sql("INSERT INTO mot_t(clave,nombre,nota) VALUES('k2','Prueba 😀','Va → viene')");ok(sql("SELECT nota FROM mot_t WHERE clave='k2'")=='Va → viene','ahora se guardan emojis y «→»')
    ok(sql("SELECT COUNT(*) FROM agora_rate_limits WHERE bucket='prueba'")=='1','las tablas del ERP conservan sus filas')
    r=php('-r','require "bootstrap.php";$db=new Agora\\Core\\DB($config["db"]);echo json_encode(Agora\\Core\\Installer::utf8mb4($db));');ok(r.stdout.strip()=='[]','repetirlo no cambia nada (idempotente)')
finally:
    if os.path.exists(apartada):shutil.move(apartada,mig)
    sql('DROP DATABASE IF EXISTS agora_lat1',base='mysql');shutil.rmtree(tmp,ignore_errors=True)
print(n,'comprobaciones')
