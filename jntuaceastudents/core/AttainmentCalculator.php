<?php
declare(strict_types=1);
namespace College;
require_once __DIR__.'/MarksNormalizer.php';

final class AttainmentCalculator
{
    /** Missing evidence is unavailable, never an attained zero. Aggregate totals are estimates. */
    public static function external(array $cos,array $rows,float $target,array $levels): array
    {
        if($target<0||$target>100||count($levels)!==3){throw new \InvalidArgumentException('Invalid attainment thresholds.');}
        $previous=-1;foreach([1,2,3] as $l){$n=MarksNormalizer::number($levels[$l],'Attainment level');if($n<$previous||$n>100){throw new \InvalidArgumentException('Attainment levels must be ordered percentages.');}$previous=$n;}
        $data=[];$modes=array_values(array_diff(array_unique(array_column($rows,'entry_mode')),['MISSING']));
        foreach($cos as $co){$id=(int)$co['id'];$attained=$evaluated=$measured=$estimated=0;
            foreach($rows as $row){$pct=null;
                if($row['entry_mode']==='DETAILED'){
                    $e=$row['evidence']??null;$c=$e['cos'][$id]??null;
                    if(($e['complete']??false)&&$c&&($c['complete']??false)&&($c['maximum']??0)>0){$pct=$c['obtained']/$c['maximum']*100;$measured++;}
                }elseif(($row['max_marks']??0)>0){$pct=MarksNormalizer::scale($row['external_marks'],$row['max_marks'],100);$estimated++;}
                if($pct!==null){$evaluated++;if($pct+0.0000001>=$target){$attained++;}}
            }
            $pct=$evaluated?$attained/$evaluated*100:null;$level=null;
            if($pct!==null){$level=0;foreach([1,2,3] as $l){if($pct+0.0000001>=$levels[$l]){$level=$l;}}}
            $data[$co['co_number']]=['co_id'=>$id,'attained_students'=>$attained,'total_students'=>count($rows),'evaluated_students'=>$evaluated,'missing_students'=>count($rows)-$evaluated,'measured_students'=>$measured,'estimated_students'=>$estimated,'cohort_percentage'=>$pct!==null?round($pct,2):null,'attainment_level'=>$level,'unmapped'=>$evaluated===0,'coverage_percentage'=>count($rows)?round($evaluated/count($rows)*100,2):null,'provisional'=>$evaluated<count($rows),'basis'=>$measured&&$estimated?'MIXED':($measured?'QUESTION_WISE':($estimated?'ESTIMATED':'UNAVAILABLE'))];
        }
        return ['status'=>1,'mode'=>count($modes)>1?'MIXED':($modes[0]??'UNAVAILABLE'),'data'=>$data];
    }
}
