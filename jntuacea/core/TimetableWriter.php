<?php
declare(strict_types=1);
namespace College;
require_once __DIR__.'/RecordScope.php';

final class TimetableWriter
{
    public function __construct(private \mysqli $db,private array $actor) {}
    public function add(int $class,string $weekday,int $hour,?int $subject,string $building,string $room,?string $activity=null): int
    {
        if(!in_array($weekday,['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'],true)){throw new \InvalidArgumentException('Invalid weekday.');}
        if(!empty($this->actor['role'])){(new RecordScope($this->db,$this->actor))->classRecord($class);if(!in_array($this->actor['role'],['hod','admin','superadmin','academic_section'],true)){throw new \DomainException('Timetable management access required.');}}
        $lock=$this->db->query("SELECT GET_LOCK(CONCAT(DATABASE(),':timetable-write'),10)")->fetch_row()[0];
        if((int)$lock!==1){throw new \RuntimeException('Another timetable update is running. Try again.');}
        $this->db->begin_transaction();
        try{
            $stmt=$this->db->prepare('SELECT * FROM classes WHERE id=? FOR UPDATE');$stmt->bind_param('i',$class);$stmt->execute();$c=$stmt->get_result()->fetch_assoc();if(!$c){throw new \DomainException('Class not found.');}
            $stmt=$this->db->prepare('SELECT * FROM class_timings WHERE id=?');$stmt->bind_param('i',$hour);$stmt->execute();$slot=$stmt->get_result()->fetch_assoc();if(!$slot||$slot['start_time']>=$slot['end_time']){throw new \DomainException('Select a valid teaching period.');}
            $stmt=$this->db->prepare('SELECT COALESCE((SELECT timing_id FROM class_timing_schedule WHERE class_id=? AND from_date<=CURRENT_DATE AND (to_date IS NULL OR to_date>=CURRENT_DATE) ORDER BY from_date DESC,id DESC LIMIT 1),timing_id) timing_id FROM classes WHERE id=?');$stmt->bind_param('ii',$class,$class);$stmt->execute();$timing=(int)$stmt->get_result()->fetch_row()[0];
            if((int)$slot['timing_id']!==$timing){throw new \DomainException('This period does not belong to the current class timing schedule.');}
            $code=trim($activity??'');
            if($subject){$stmt=$this->db->prepare('SELECT subcode FROM subjects WHERE id=? AND class_id=?');$stmt->bind_param('ii',$subject,$class);$stmt->execute();$r=$stmt->get_result()->fetch_row();if(!$r){throw new \DomainException('Select a subject belonging to this class.');}$code=$r[0];}
            elseif($code===''){throw new \InvalidArgumentException('Select a subject or enter an activity.');}
            $stmt=$this->db->prepare("SELECT t.id,t.class_id,t.building_name,t.class_hall_name,t.subject_id FROM class_timetables t JOIN classes c ON c.id=t.class_id JOIN class_timings ct ON ct.id=t.Hour WHERE t.weekday=? AND ct.start_time<? AND ct.end_time>? AND c.start_date<=? AND c.end_date>=? AND ((t.class_id=? AND (? IS NULL OR t.subject_id IS NULL OR t.subject_id=?)) OR (t.class_id!=? AND ?<>'' AND ?<>'' AND t.building_name=? AND t.class_hall_name=?) OR (t.class_id!=? AND ? IS NOT NULL AND EXISTS(SELECT 1 FROM faculty_sub a JOIN faculty_sub b ON b.faculty_id=a.faculty_id WHERE a.sub_id=? AND b.sub_id=t.subject_id))) FOR UPDATE");
            $stmt->bind_param('sssssiiiiissssiii',$weekday,$slot['end_time'],$slot['start_time'],$c['end_date'],$c['start_date'],$class,$subject,$subject,$class,$building,$room,$building,$room,$class,$subject,$subject);$stmt->execute();
            if($conflict=$stmt->get_result()->fetch_assoc()){
                if((int)$conflict['class_id']===$class){
                    if($subject!==null&&!empty($conflict['subject_id'])&&(int)$conflict['subject_id']===$subject){throw new \DomainException('This subject is already scheduled for this class in this period.');}
                    throw new \DomainException('This period overlaps an existing class activity or schedule.');
                }
                if($building!==''&&$room!==''&&$conflict['building_name']===$building&&$conflict['class_hall_name']===$room){
                    throw new \DomainException("Room {$building} - {$room} is already booked for another class at this time.");
                }
                throw new \DomainException('Assigned faculty has a scheduling conflict with another class at this time.');
            }
            $stmt=$this->db->prepare('INSERT INTO class_timetables(class_id,weekday,Hour,subject_code,subject_id,building_name,class_hall_name) VALUES (?,?,?,?,?,?,?)');$stmt->bind_param('isisiss',$class,$weekday,$hour,$code,$subject,$building,$room);$stmt->execute();$id=$this->db->insert_id;
            if(!empty($this->actor['userid'])){
                $actor=(int)$this->actor['userid'];$username=$this->actor['user'];$role=$this->actor['role'];$entity=(string)$id;
                $stmt=$this->db->prepare("INSERT INTO audit_logs(user_id,username,role,action,entity_type,entity_id,status,details) VALUES (?,?,?,'ADD_TIMETABLE_ENTRY','TIMETABLE',?,'SUCCESS','Saved after class, faculty and room conflict checks.')");$stmt->bind_param('isss',$actor,$username,$role,$entity);$stmt->execute();
            }
            $this->db->commit();return $id;
        }catch(\Throwable $e){$this->db->rollback();throw $e;}
        finally{$this->db->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(),':timetable-write'))");}
    }
}
