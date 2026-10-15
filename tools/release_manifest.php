<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/jntuacea/core/Clock.php';
\College\Clock::configure();
if(PHP_SAPI!=='cli'){exit(1);}
$workspace=dirname(__DIR__);$files=[];
foreach(['jntuacea','jntuaceastudents'] as $portal){
 $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($workspace.'/'.$portal,FilesystemIterator::SKIP_DOTS));
 foreach($iterator as $file){if(!$file->isFile()){continue;}$relative=substr($file->getPathname(),strlen($workspace)+1);
  if(preg_match('#/(?:vendor|uploads|logs|ignore_old|\.git|\.devin|node_modules)/#',$relative)&&!preg_match('#/uploads/student_docs/\.htaccess$#',$relative)){continue;}
  if(str_contains(basename($relative),'.env')||(!in_array($file->getExtension(),['php','js','css','sql','md'],true)&&!in_array(basename($relative),['.htaccess','.gitignore','composer.json','composer.lock','release.json'],true))){continue;}
  $files[$relative]=hash_file('sha256',$file->getPathname());
 }
}
foreach(['.htaccess','standardisation-implementation-2026-10-08.md','historical-data-review-2026-10-08.md'] as $file){if(is_file($workspace.'/'.$file)){$files[$file]=hash_file('sha256',$workspace.'/'.$file);}}
ksort($files);$manifest=['release'=>'2026.10.08.2','generated_at'=>date(DATE_ATOM),'files'=>$files];
$options=getopt('',['output:','verify:']);
if(isset($options['verify'])){
 $saved=json_decode(file_get_contents($options['verify']),true,512,JSON_THROW_ON_ERROR);
 if(($saved['files']??[])!==$files){$different=array_keys(array_diff_assoc($files,$saved['files']??[])+array_diff_assoc($saved['files']??[],$files));fwrite(STDERR,'Release differs: '.implode(', ',$different).PHP_EOL);exit(1);}
 echo 'Release checksums verified: '.count($files).' files.'.PHP_EOL;exit;
}
$json=json_encode($manifest,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
if(isset($options['output'])){file_put_contents($options['output'],$json);echo 'Release manifest written: '.count($files).' source files.'.PHP_EOL;}else{echo $json.PHP_EOL;}
