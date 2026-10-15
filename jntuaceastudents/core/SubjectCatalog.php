<?php
declare(strict_types=1);
namespace College;
final class SubjectCatalog
{
    public static function visible(\mysqli $db,array $actor): array
    {
        $role=$actor['role']??'';$sql='SELECT DISTINCT s.id,s.subcode,s.sub_fullname,c.classname AS class_name FROM subjects s JOIN classes c ON c.id=s.class_id';
        if($role==='faculty'){$sql.=' JOIN faculty_sub fs ON fs.sub_id=s.id JOIN faculties f ON f.id=fs.faculty_id WHERE f.username=?';}
        elseif($role==='hod'){$sql.=' JOIN specialization sp ON sp.id=c.spec_id JOIN departments d ON d.id=sp.dept_id WHERE d.username=?';}
        elseif(!in_array($role,['admin','academic_section','superadmin'],true)){return [];}
        $stmt=$db->prepare($sql.' ORDER BY c.id DESC,s.subcode');if(in_array($role,['faculty','hod'],true)){$stmt->bind_param('s',$actor['user']);}$stmt->execute();return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
