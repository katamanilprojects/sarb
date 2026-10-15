<?php
declare(strict_types=1);
namespace Services;
require_once __DIR__.'/../dbcredentials.class.php';
require_once __DIR__.'/FeatureManager.php';

final class AcademicReviewService extends \DBCredentials
{
    public function decide(string $type,int $id,string $decision,string $remarks,?string $due=null,?string $followup=null): void
    {
        $role=$_SESSION['role']??'';$actor=(int)($_SESSION['userid']??0);$remarks=trim($remarks);
        if(!$actor || $remarks===''){throw new \DomainException('Enter review observations or execution evidence.');}
        $module=$type==='COURSE'?'MOD_LESSON_PLAN':'MOD_CQI_ACTION_TRACKING';
        if (!FeatureManager::canAction($module,$role==='hod'?'approve':'edit',$role)) { throw new \DomainException('This review action is disabled for your account.'); }
        $this->conn->begin_transaction();
        try {
            $table=match($type){'COURSE'=>'course_completion_audits','CQI'=>'course_cqi_actions',default=>throw new \InvalidArgumentException('Invalid review type.')};
            $stmt=$this->conn->prepare("SELECT * FROM $table WHERE id=? FOR UPDATE");$stmt->bind_param('i',$id);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();
            if(!$row){throw new \DomainException('Record not found.');}$this->assertSubjectScope((int)$row['sub_id']);
            $sub=(int)$row['sub_id'];$lock=$this->conn->prepare('SELECT id FROM subjects WHERE id=? FOR UPDATE');$lock->bind_param('i',$sub);$lock->execute();$lock->store_result();
            if($type==='COURSE'){
                if($role!=='hod' || !in_array($decision,['APPROVED','REJECTED'],true) || $row['faculty_signoff_status']!=='SUBMITTED' || $row['hod_approval_status']!=='PENDING'){throw new \DomainException('Only the department HOD may review a pending submission.');}
                $from='SUBMITTED';$to=$decision;
                $stmt=$this->conn->prepare('UPDATE course_completion_audits SET hod_approval_status=?,hod_reviewed_by=?,hod_approval_at=NOW(),hod_remarks=? WHERE id=?');$stmt->bind_param('sisi',$decision,$actor,$remarks,$id);
            }elseif(in_array($decision,['APPROVED','REVISION_REQUESTED'],true)){
                if($role!=='hod'||$row['hod_review_status']!=='PENDING'){throw new \DomainException('Only the department HOD may review a pending CQI action.');}
                if($due!==null && $due!=='' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$due) || !($parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$due)) || $parsed->format('Y-m-d')!==$due)){throw new \InvalidArgumentException('Invalid due date.');}
                $due=$due?:null;$from='PENDING';$to=$decision;
                $stmt=$this->conn->prepare('UPDATE course_cqi_actions SET hod_review_status=?,reviewed_by=?,reviewed_at=NOW(),hod_remarks=?,due_date=? WHERE id=?');$stmt->bind_param('sissi',$decision,$actor,$remarks,$due,$id);
            }elseif($decision==='REVISED'){
                if($role!=='faculty'||(int)$row['faculty_id']!==(int)($_SESSION['facid']??0)||$row['hod_review_status']!=='REVISION_REQUESTED'){throw new \DomainException('Only the assigned faculty may revise a returned action.');}
                $from='REVISION_REQUESTED';$to='PENDING';$stmt=$this->conn->prepare("UPDATE course_cqi_actions SET action_proposed=?,hod_review_status='PENDING',reviewed_by=NULL,reviewed_at=NULL,hod_remarks=NULL,submitted_at=NOW() WHERE id=?");$stmt->bind_param('si',$remarks,$id);
            }elseif($decision==='COMPLETED'){
                if($role!=='faculty'||(int)$row['faculty_id']!==(int)($_SESSION['facid']??0)||$row['hod_review_status']!=='APPROVED'||$row['completed_at']){throw new \DomainException('The assigned faculty may record completion once after approval.');}
                $from='APPROVED';$to='COMPLETED';$stmt=$this->conn->prepare('UPDATE course_cqi_actions SET execution_evidence=?,completed_at=NOW() WHERE id=?');$stmt->bind_param('si',$remarks,$id);
            }elseif($decision==='CLOSED'){
                if($role!=='hod'||!$row['completed_at']||$row['closed_at']||trim($followup??'')===''){throw new \DomainException('The HOD must record follow-up findings before closing a completed action.');}
                $from='COMPLETED';$to='CLOSED';$stmt=$this->conn->prepare('UPDATE course_cqi_actions SET followup_measure=?,closed_by=?,closed_at=NOW() WHERE id=?');$stmt->bind_param('sii',$followup,$actor,$id);
            }else{throw new \InvalidArgumentException('Invalid workflow transition.');}
            $stmt->execute();
            $stmt=$this->conn->prepare('INSERT INTO workflow_review_history(entity_type,entity_id,actor_user_id,from_status,to_status,remarks) VALUES (?,?,?,?,?,?)');$stmt->bind_param('siisss',$type,$id,$actor,$from,$to,$remarks);$stmt->execute();
            if(!$this->dbActivityLog($actor,'REVIEW_'.$type,$remarks,$role,$type,(string)$id)){throw new \RuntimeException('Unable to save review audit trail.');}
            $this->conn->commit();
        }catch(\Throwable $e){$this->conn->rollback();throw $e;}
    }

    public function proposeCqi(int $subject,int $coId,string $actionType,string $description): void
    {
        $this->assertSubjectScope($subject);
        if(($_SESSION['role']??'')!=='faculty'||!FeatureManager::canRoleWrite('MOD_CQI_ACTION_TRACKING')){throw new \DomainException('Faculty CQI entry is disabled.');}
        if(!in_array($actionType,['REMEDIAL_CLASS','PEDAGOGICAL_CHANGE','TUTORIAL_HOURS','ASSIGNMENT_REVISION','CONTENT_BEYOND_SYLLABUS'],true)||trim($description)===''){throw new \InvalidArgumentException('Select an action type and describe the proposed action.');}
        $stmt=$this->conn->prepare('SELECT co.target_threshold_percent,c.acad_year FROM course_outcomes co JOIN subjects s ON s.id=co.sub_id JOIN classes c ON c.id=s.class_id WHERE co.id=? AND co.sub_id=?');$stmt->bind_param('ii',$coId,$subject);$stmt->execute();$context=$stmt->get_result()->fetch_assoc();
        if(!$context){throw new \DomainException('Select a CO belonging to this subject.');}
        require_once __DIR__.'/../coattainment.class.php';$data=json_decode((new \COAttainmentEngine())->getComprehensiveAttainment($subject),true,512,JSON_THROW_ON_ERROR);
        $attained=null;foreach($data['cos']??[] as $co){if((int)$co['co_id']===$coId){$attained=$co['cia_pct'];}}
        if($attained===null || $context['target_threshold_percent']===null){throw new \DomainException('Recorded CIA attainment and a configured CO target are required. Enter assessment evidence before proposing an action.');}
        $target=(float)$context['target_threshold_percent'];$attained=(float)$attained;$gap=round(max(0,$target-$attained),2);$faculty=(int)$_SESSION['facid'];$actor=(int)$_SESSION['userid'];
        $this->conn->begin_transaction();
        try{
            $stmt=$this->conn->prepare('INSERT INTO course_cqi_actions(sub_id,co_id,academic_year,target_benchmark,attained_percentage,attainment_gap,action_proposed,action_type,faculty_id) VALUES (?,?,?,?,?,?,?,?,?)');$stmt->bind_param('iisdddssi',$subject,$coId,$context['acad_year'],$target,$attained,$gap,$description,$actionType,$faculty);$stmt->execute();$id=$this->conn->insert_id;
            if(!$this->dbActivityLog($actor,'PROPOSE_CQI_ACTION','Proposed action from recorded CIA attainment and the stored CO target; provisional until HOD review.','faculty','CQI',(string)$id)){throw new \RuntimeException('Unable to save CQI audit trail.');}
            $this->conn->commit();
        }catch(\Throwable $e){$this->conn->rollback();throw $e;}
    }

    public function queue(): array
    {
        $role=$_SESSION['role']??'';$user=$_SESSION['user']??'';
        if(!in_array($role,['hod','faculty'],true)){throw new \DomainException('Faculty or HOD access required.');}
        $filter=$role==='hod'?'d.username=?':'EXISTS(SELECT 1 FROM faculty_sub fs JOIN faculties f ON f.id=fs.faculty_id WHERE fs.sub_id=s.id AND f.username=?)';
        $rows=[];
        foreach(['COURSE'=>'course_completion_audits','CQI'=>'course_cqi_actions'] as $type=>$table){
            $stmt=$this->conn->prepare("SELECT a.*,s.subcode,s.sub_fullname FROM $table a JOIN subjects s ON s.id=a.sub_id JOIN classes c ON c.id=s.class_id JOIN specialization sp ON sp.id=c.spec_id JOIN departments d ON d.id=sp.dept_id WHERE $filter ORDER BY a.id DESC LIMIT 200");
            $stmt->bind_param('s',$user);$stmt->execute();$rows[$type]=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }return $rows;
    }
}
