<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/jntuacea/services/AIJobService.php';
$options=getopt('',['limit:','database:']);$limit=(int)($options['limit']??10);if($limit<1||$limit>100){throw new InvalidArgumentException('Limit must be 1–100.');}
if(isset($options['database'])){require_once dirname(__DIR__).'/.env.php';$name=$options['database'];if(!str_starts_with($name,'cea_standardisation_test_')||$name===DB_NAME){throw new DomainException('The database override is restricted to isolated tests.');}require_once dirname(__DIR__).'/jntuacea/core/Connection.php';$db=new mysqli(DB_HOST,DB_USER,DB_PASS,$name);$db->set_charset('utf8mb4');(new ReflectionProperty(College\Connection::class,'connection'))->setValue(null,$db);}
$service=new Services\AIJobService();$db=$service->getConnection();$lock='cea-ai-worker:'.$db->query('SELECT DATABASE()')->fetch_row()[0];$stmt=$db->prepare('SELECT GET_LOCK(?,0)');$stmt->bind_param('s',$lock);$stmt->execute();if((int)$stmt->get_result()->fetch_row()[0]!==1){echo "An AI worker is already active.\n";exit;}
try{$done=0;while($done<$limit&&$service->runOne()){$done++;}echo 'Processed '.$done." AI jobs.\n";}finally{$stmt=$db->prepare('SELECT RELEASE_LOCK(?)');$stmt->bind_param('s',$lock);$stmt->execute();}
