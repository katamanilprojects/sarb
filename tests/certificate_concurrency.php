<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit(1);}
require dirname(__DIR__).'/.env.php';require dirname(__DIR__).'/jntuacea/core/CertificateNumber.php';
$options=getopt('',['database:','child']);$database=$options['database']??'';
if(!str_starts_with($database,'cea_standardisation_test_')||$database===DB_NAME){throw new RuntimeException('An isolated test database is required.');}
if(isset($options['child'])){$db=new mysqli(DB_HOST,DB_USER,DB_PASS,$database);echo College\CertificateNumber::reserve($db,'BONAFIDE');exit;}
$children=[];
for($n=0;$n<12;$n++){$process=proc_open([PHP_BINARY,__FILE__,'--database='.$database,'--child'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$children[]=[$process,$pipes];}
$numbers=[];
foreach($children as [$process,$pipes]){$number=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0||$error!==''||!preg_match('#^JNTUACEA/ACAD/\d{4}/BONA/\d+$#D',$number)){throw new RuntimeException('A concurrent reservation failed: '.$error);}$numbers[]=$number;}
if(count(array_unique($numbers))!==12){throw new RuntimeException('Duplicate concurrent certificate numbers.');}
echo "Passed: 12 concurrent reservations produced 12 unique numbers.\n";
