<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit(1);}
require_once dirname(__DIR__).'/jntuacea/core/AIProvider.php';
$options=getopt('',['fake-endpoint:']);$endpoint=$options['fake-endpoint']??'';
if($endpoint!=='http://127.0.0.1:8766'){throw new DomainException('Start the isolated loopback fixture; external providers are not allowed in this suite.');}
$p=['kind'=>'OLLAMA','endpoint'=>$endpoint,'model'=>'test-valid','credential_env'=>'','timeout_seconds'=>5];$evidence=['CO1'=>['cohort_percentage'=>65,'basis'=>'ESTIMATED']];$checks=0;
$result=College\AIProvider::generate($p,$evidence);if(empty($result['draft']['observations'])){throw new RuntimeException('Expected valid fake provider output.');}$checks++;
foreach(['test-redirect','test-failure','test-malformed','test-oversize','test-numeric'] as $model){$p['model']=$model;$failed=false;try{College\AIProvider::generate($p,$evidence);}catch(Throwable $e){$failed=true;}if(!$failed){throw new RuntimeException('Unsafe provider response accepted: '.$model);}$checks++;}
$p['endpoint']='http://169.254.169.254';$failed=false;try{College\AIProvider::generate($p,$evidence);}catch(DomainException $e){$failed=true;}if(!$failed){throw new RuntimeException('Link-local provider address accepted.');}$checks++;
echo "Passed $checks real HTTP adapter checks using only the loopback fake provider.\n";
