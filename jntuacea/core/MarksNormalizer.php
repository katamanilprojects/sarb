<?php
declare(strict_types=1);
namespace College;

/** Arithmetic shared by imports and the existing detailed/aggregate entry screens. */
final class MarksNormalizer
{
    public static function number(mixed $value, string $label): float
    {
        if (!is_numeric($value) || !is_finite((float)$value) || (float)$value<0) {
            throw new \InvalidArgumentException($label.' must be a finite nonnegative number.');
        }
        return (float)$value;
    }

    public static function scale(mixed $marks, mixed $sourceMax, mixed $targetMax): float
    {
        $marks=self::number($marks,'Marks');$sourceMax=self::number($sourceMax,'Gross maximum');$targetMax=self::number($targetMax,'Net maximum');
        if ($sourceMax<=0 || $targetMax<=0 || $marks>$sourceMax+0.000001) {throw new \InvalidArgumentException('Marks and positive gross/net maxima must agree.');}
        return $marks/$sourceMax*$targetMax;
    }

    /** Select complete options once per student, then attribute their questions to COs. */
    public static function questions(array $questions, array $scores, array $groups=[], ?float $targetMax=null): array
    {
        $byLabel=[];$byId=[];
        foreach($questions as $q){
            $label=strtolower(trim((string)$q['question_label']));$id=(int)$q['id'];$max=self::number($q['marks'],'Question maximum');
            if($id<1 || $label==='' || isset($byLabel[$label]) || $max<=0){throw new \InvalidArgumentException('Question identifiers, labels and positive maxima must be unique.');}
            $q['marks']=$max;$q['cos']=array_values(array_unique(array_map('intval',$q['cos']??[])));$byLabel[$label]=$q;$byId[$id]=$label;
        }
        $values=[];
        foreach($scores as $id=>$value){
            if(!isset($byId[(int)$id])){throw new \InvalidArgumentException('Unknown question in marks.');}
            if($value===null || trim((string)$value)===''){continue;}
            $value=self::number($value,'Question score');$label=$byId[(int)$id];
            if($value>$byLabel[$label]['marks']+0.000001){throw new \InvalidArgumentException('Question score exceeds its maximum.');}$values[$label]=$value;
        }
        $grouped=[];$selected=[];$factors=[];$decisions=[];$complete=true;
        foreach($groups as $index=>$group){
            $options=$group['options']??[];$take=(int)($group['select']??1);
            if(!is_array($options)||$take!=($group['select']??1)){throw new \InvalidArgumentException('Invalid choice group.');}
            if($take<1 || $take>count($options)){throw new \InvalidArgumentException('Invalid number of choices.');}
            $ranked=[];
            foreach($options as $oi=>$labels){if(!is_array($labels)||!$labels){throw new \InvalidArgumentException('Each choice option needs question labels.');}$sum=0.0;$max=0.0;$attempted=false;$labels=array_map(static fn($v)=>strtolower(trim((string)$v)),$labels);
                foreach($labels as $label){if(!isset($byLabel[$label])||isset($grouped[$label])){throw new \InvalidArgumentException('Choice questions must exist and belong to one option only.');}$grouped[$label]=true;$max+=$byLabel[$label]['marks'];$sum+=$values[$label]??0;$attempted=$attempted||array_key_exists($label,$values);}
                $cap=isset($group['max_marks'])?self::number($group['max_marks'],'Choice maximum'):$max;if($cap<=0){throw new \InvalidArgumentException('Choice maxima must be positive.');}
                $ranked[]=['raw_score'=>$sum,'raw_max'=>$max,'cap'=>$cap,'index'=>$oi,'labels'=>$labels,'score'=>min($sum,$cap),'max'=>min($max,$cap),'attempted'=>$attempted];
            }
            // A selected option must contain every sub-question (explicit zero is valid).
            usort($ranked,static fn($a,$b)=>($b['attempted']<=>$a['attempted'])?:($b['score']<=>$a['score'])?:($a['index']<=>$b['index']));
            foreach(array_slice($ranked,0,$take) as $option){foreach($option['labels'] as $label){$selected[$label]=true;$factors[$label]=[$option['raw_score']>0?$option['score']/$option['raw_score']:1,$option['max']/$option['raw_max']];if(!array_key_exists($label,$values)){$complete=false;}}$decisions[]=['group'=>$index,'option'=>$option['index'],'labels'=>$option['labels'],'score'=>$option['score'],'options'=>$ranked];}
        }
        foreach($byLabel as $label=>$q){if(!isset($grouped[$label])){$selected[$label]=true;if(!array_key_exists($label,$values)){$complete=false;}}}
        $gross=$grossMax=0.0;$cos=[];$selectedQuestions=[];
        foreach($selected as $label=>$_){$q=$byLabel[$label];$f=$factors[$label]??[1,1];$obt=($values[$label]??0)*$f[0];$q['marks']*=$f[1];$gross+=$obt;$grossMax+=$q['marks'];$weight=count($q['cos'])?1/count($q['cos']):0;$selectedQuestions[]=['id'=>(int)$q['id'],'label'=>$q['question_label'],'obtained'=>$obt,'maximum'=>$q['marks'],'cos'=>$q['cos'],'complete'=>array_key_exists($label,$values)];
            foreach($q['cos'] as $co){if($co<1){throw new \InvalidArgumentException('Invalid CO mapping.');}$cos[$co]??=['obtained'=>0.0,'maximum'=>0.0,'complete'=>true];$cos[$co]['obtained']+=$obt*$weight;$cos[$co]['maximum']+=$q['marks']*$weight;$cos[$co]['complete']=$cos[$co]['complete']&&array_key_exists($label,$values);}
        }
        foreach($cos as &$co){$co['percentage']=$co['complete']&&$co['maximum']>0?$co['obtained']/$co['maximum']*100:null;}unset($co);
        $netMax=$targetMax??$grossMax;
        return ['gross'=>$gross,'gross_max'=>$grossMax,'net'=>$grossMax>0?self::scale($gross,$grossMax,$netMax):null,'net_max'=>$netMax,'complete'=>$complete,'cos'=>$cos,'selected_options'=>$decisions,'selected_questions'=>$selectedQuestions,'co_mapping_strategy'=>'EQUAL_SHARE'];
    }

    public static function grade(string $grade, array $bands, string $strategy='NONE'): array
    {
        foreach($bands as $band){if(strcasecmp(trim($grade),(string)$band['grade'])!==0){continue;}
            $low=self::number($band['min_percent'],'Grade lower limit');$high=self::number($band['max_percent'],'Grade upper limit');
            if($high<$low||$high>100){throw new \InvalidArgumentException('Invalid grade interval.');}
            $pct=match($strategy){'LOWER_BOUND'=>$low,'MIDPOINT'=>($low+$high)/2,'UPPER_BOUND'=>$high,'NONE'=>null,default=>throw new \InvalidArgumentException('Invalid grade estimation policy.')};
            return ['percentage'=>$pct,'points'=>self::number($band['points'],'Grade points'),'range'=>[$low,$high]];
        }
        throw new \InvalidArgumentException('Grade is not present in the configured regulation profile.');
    }
}
