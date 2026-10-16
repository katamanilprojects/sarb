<?php
declare(strict_types=1);
namespace College;

/** Reviewed document formulas; activation is explicit, never a fallback during calculation. */
final class VerifiedRegulations
{
    public static function definition(array $regulation): ?array
    {
        $program=preg_replace('/[^A-Z0-9]/','',strtoupper($regulation['prog_shortname']));$reg=strtoupper($regulation['regulation']);
        $pg=$regulation['program_level']==='PG';
        if($pg && $program==='MCA' && $reg==='R20'){
            $source='mca_r20_regulations.pdf';$section='4(i–vii), 5';$weight=.7;$required=[1,2,3];$lab=[11];$maxima=[1=>30,2=>30,3=>10];
            $bands=[['S',10,95,100],['A1',9.5,90,94],['A2',9,85,89],['A3',8.5,80,84],['B1',8,75,79],['B2',7.5,70,74],['C1',7,65,69],['C2',6.5,60,64],['D1',6,55,59],['D2',5.5,50,54],['F',0,0,49]];
            $settings=['theory_continuous_eval_marks'=>0,'lab_cia_record_marks'=>0];
        }elseif($pg && $program==='MTECH' && in_array($reg,['R21','R25'],true)){
            $r25=$reg==='R25';$source=$r25?'mtech_r25_regulations.pdf':'mtech_r21_regulations.pdf';$section=$r25?'8.1–8.4, 15':'5.1–5.4, 6';$weight=$r25?.8:.7;$required=$r25?[1,2,4]:[1,2,3];$lab=[11,12];$maxima=[1=>30,2=>30,($r25?4:3)=>10];
            $bands=[['S',10,90,100],['A',9,80,89],['B',8,70,79],['C',7,60,69],['D',6,50,59],['F',0,0,49]];
            $settings=['theory_continuous_eval_marks'=>$r25?10:0,'lab_cia_day_to_day_marks'=>10,'lab_cia_internal_test_marks'=>20,'lab_cia_record_marks'=>10];
        }elseif(!$pg && $program==='BTECH' && in_array($reg,['R20','R23','R26'],true)){
            $source='btech_r23_regulations.pdf';$section='9, 19; R20/R26 equivalence explicitly approved by user';$weight=.8;$required=[1,2];$lab=[1];$maxima=[1=>30,2=>30];
            $bands=[['A+',10,90,100],['A',9,80,89],['B',8,70,79],['C',7,60,69],['D',6,50,59],['E',5,40,49],['F',0,0,39]];
            $settings=['theory_mid_subjective_condensed'=>15,'theory_mid_objective_marks'=>10,'theory_assignment_marks'=>5,'lab_cia_day_to_day_marks'=>15,'lab_cia_internal_test_marks'=>15];
        }else{return null;}
        $theory=['kind'=>$pg?'PG_THEORY':'UG_THEORY','required_assessments'=>$required,'better_weight'=>$weight,'lesser_weight'=>round(1-$weight,2),'component_maxima'=>$maxima,'rounding'=>'NEAREST_INTEGER'];
        $laboratory=['kind'=>$pg?'PG_LAB':'UG_LAB','required_assessments'=>$lab,'component_maxima'=>array_combine($lab,count($lab)===2?[30,10]:[$pg?40:30]),'rounding'=>'NEAREST_INTEGER'];
        $settings+=['theory_cia_total_marks'=>$pg?40:30,'theory_mid_better_weight'=>$weight,'theory_mid_lesser_weight'=>round(1-$weight,2)];
        $schemes=['theory'=>$theory,'pe'=>$theory,'oe'=>$theory,'lab'=>$laboratory];
        $grades=array_map(static fn($b)=>['grade'=>$b[0],'points'=>$b[1],'min_percent'=>$b[2],'max_percent'=>$b[3]],$bands);
        $sourceHashes=['mca_r20_regulations.pdf'=>'d9266dc1397a603979f73697e427ad68556af9c71da9e55d5b7ea462c187d967','mtech_r21_regulations.pdf'=>'8b6e55c5d528b590e38e864c899ded2d17c4886d921dfdf8528a1593690b9556','btech_r23_regulations.pdf'=>'ff69c5b814088cc6ddafadc0ab850f5442f49d9b2d0f18dca88a11e0ca09aa4a','mtech_r25_regulations.pdf'=>'35f1e84b23ece5fb8338247bb3c181ea13174e3c74234829bbec5d0ac9e3ab17'];
        return ['source_sha256'=>$sourceHashes[$source],'source'=>$source,'section'=>$section,'settings'=>$settings,'schemes'=>$schemes,'grade_bands'=>$grades,'internal_max'=>$pg?40:30,'external_max'=>$pg?60:70];
    }
}
