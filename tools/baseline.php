<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/.env.php';
$options=getopt('',['database:','apply']);$db=new mysqli(DB_HOST,DB_USER,DB_PASS,$options['database']??DB_NAME);$db->set_charset('utf8mb4');
$file=dirname(__DIR__).'/database/baselines/20261007_accreditation.sql';$sql=file_get_contents($file);
preg_match_all('/CREATE TABLE IF NOT EXISTS `([^`]+)`/',$sql,$matches);$tables=array_unique($matches[1]);
foreach($tables as $table){$stmt=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$stmt->bind_param('s',$table);$stmt->execute();if((int)$stmt->get_result()->fetch_row()[0]!==1){throw new RuntimeException('Baseline table missing: '.$table);}}
preg_match_all('/\x27(MOD_[A-Z0-9_]+)\x27/',$sql,$matches);$features=array_unique($matches[1]);
foreach($features as $feature){$stmt=$db->prepare('SELECT COUNT(*) FROM system_feature_modules WHERE module_key=?');$stmt->bind_param('s',$feature);$stmt->execute();if((int)$stmt->get_result()->fetch_row()[0]!==1){throw new RuntimeException('Baseline feature missing: '.$feature);}}
preg_match_all("/SELECT MIN\\(r.id\\), r.regulation, '[^']+', '([^']+)'/",$sql,$settingMatches);
foreach(array_unique($settingMatches[1]) as $key){$stmt=$db->prepare('SELECT COUNT(*) FROM academic_settings WHERE setting_key=?');$stmt->bind_param('s',$key);$stmt->execute();if(!(int)$stmt->get_result()->fetch_row()[0]){throw new RuntimeException('Baseline setting missing: '.$key);}}
if(isset($options['apply'])){
 $db->query('CREATE TABLE IF NOT EXISTS schema_migrations(version varchar(100) PRIMARY KEY,checksum char(64) NOT NULL,applied_at timestamp DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
 $version='baseline_20261007_accreditation.sql';$hash=hash_file('sha256',$file);
 $stmt=$db->prepare('SELECT checksum FROM schema_migrations WHERE version=?');$stmt->bind_param('s',$version);$stmt->execute();$old=$stmt->get_result()->fetch_row();
 if($old&&!hash_equals($old[0],$hash)){throw new RuntimeException('Baseline checksum changed.');}
 $stmt=$db->prepare('INSERT IGNORE INTO schema_migrations(version,checksum) VALUES (?,?)');$stmt->bind_param('ss',$version,$hash);$stmt->execute();
}
echo 'Existing accreditation baseline verified: '.count($tables).' tables and '.count($features).' feature keys; no seed values overwritten.'.PHP_EOL;
