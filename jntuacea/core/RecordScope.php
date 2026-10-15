<?php
declare(strict_types=1);
namespace College;

/** Resource authorization uses the logged-in username, never a posted actor ID. */
final class RecordScope
{
    public function __construct(private \mysqli $db, private array $actor) {}

    private function exists(string $sql, string $types, mixed ...$args): bool
    {
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$args);
        $stmt->execute();
        return (bool)$stmt->get_result()->fetch_row();
    }

    public function subject(int $id): void
    {
        $role = $this->actor['role'] ?? '';
        $user = $this->actor['user'] ?? '';
        $allowed = match ($role) {
            'superadmin', 'admin', 'academic_section' => $this->exists('SELECT id FROM subjects WHERE id=?', 'i', $id),
            'faculty' => $this->exists('SELECT fs.sub_id FROM faculty_sub fs JOIN faculties f ON f.id=fs.faculty_id WHERE fs.sub_id=? AND f.username=?', 'is', $id, $user),
            'hod' => $this->exists('SELECT s.id FROM subjects s JOIN classes c ON c.id=s.class_id JOIN specialization sp ON sp.id=c.spec_id JOIN departments d ON d.id=sp.dept_id WHERE s.id=? AND d.username=?', 'is', $id, $user),
            'student' => $this->exists('SELECT ss.sub_id FROM student_sub ss JOIN students st ON st.id=ss.stu_id WHERE ss.sub_id=? AND st.username=?', 'is', $id, $user),
            default => false,
        };
        if (!$allowed) { throw new \DomainException('You do not have access to this subject.'); }
    }

    public function curriculumSubject(int $id): void
    {
        $role=$this->actor['role']??'';$user=$this->actor['user']??'';
        $allowed=match($role){
            'superadmin','admin','academic_section'=>$this->exists('SELECT id FROM curriculum_subjects WHERE id=?','i',$id),
            'hod'=>$this->exists('SELECT cs.id FROM curriculum_subjects cs JOIN specialization sp ON sp.id=cs.spec_id JOIN departments d ON d.id=sp.dept_id WHERE cs.id=? AND d.username=?','is',$id,$user),
            'faculty'=>$this->exists('SELECT s.id FROM subjects s JOIN faculty_sub fs ON fs.sub_id=s.id JOIN faculties f ON f.id=fs.faculty_id WHERE s.curr_sub_id=? AND f.username=?','is',$id,$user),
            'student'=>$this->exists('SELECT s.id FROM subjects s JOIN student_sub ss ON ss.sub_id=s.id JOIN students st ON st.id=ss.stu_id WHERE s.curr_sub_id=? AND st.username=?','is',$id,$user),
            default=>false,
        };
        if(!$allowed){throw new \DomainException('You do not have access to this curriculum subject.');}
    }

    public function classRecord(int $id): void
    {
        $role = $this->actor['role'] ?? '';
        $user = $this->actor['user'] ?? '';
        $allowed = match ($role) {
            'superadmin', 'admin', 'academic_section' => $this->exists('SELECT id FROM classes WHERE id=?', 'i', $id),
            'hod' => $this->exists('SELECT c.id FROM classes c JOIN specialization sp ON sp.id=c.spec_id JOIN departments d ON d.id=sp.dept_id WHERE c.id=? AND d.username=?', 'is', $id, $user),
            'faculty' => $this->exists('SELECT s.id FROM subjects s JOIN faculty_sub fs ON fs.sub_id=s.id JOIN faculties f ON f.id=fs.faculty_id WHERE s.class_id=? AND f.username=?', 'is', $id, $user),
            'student' => $this->exists('SELECT id FROM students WHERE class_id=? AND username=?', 'is', $id, $user),
            default => false,
        };
        if (!$allowed) { throw new \DomainException('You do not have access to this class.'); }
    }

    public function student(int $studentId, ?int $subjectId = null): void
    {
        if (($this->actor['role'] ?? '') === 'student' && !$this->exists('SELECT id FROM students WHERE id=? AND username=?', 'is', $studentId, $this->actor['user'] ?? '')) {
            throw new \DomainException('You do not have access to this student record.');
        }
        if ($subjectId !== null) {
            $this->subject($subjectId);
            if (!$this->exists('SELECT id FROM student_sub WHERE stu_id=? AND sub_id=?', 'ii', $studentId, $subjectId)) {
                throw new \DomainException('The student is not enrolled in this subject.');
            }
        } elseif (($this->actor['role'] ?? '') === 'student') {
            if (!$this->exists('SELECT id FROM students WHERE id=? AND username=?', 'is', $studentId, $this->actor['user'] ?? '')) {
                throw new \DomainException('You do not have access to this student record.');
            }
        } else {
            $stmt = $this->db->prepare('SELECT class_id FROM students WHERE id=?');
            $stmt->bind_param('i', $studentId); $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if (!$row) { throw new \DomainException('Student record not found.'); }
            $this->classRecord((int)$row['class_id']);
        }
    }

    public function documentOwner(string $roll): void
    {
        $role = $this->actor['role'] ?? '';
        if (in_array($role, ['superadmin', 'admin', 'academic_section'], true)) { return; }
        if ($role === 'student' && strcasecmp($roll, $this->actor['user'] ?? '') === 0) { return; }
        if ($role === 'hod' && $this->exists('SELECT st.id FROM students st JOIN classes c ON c.id=st.class_id JOIN specialization sp ON sp.id=c.spec_id JOIN departments d ON d.id=sp.dept_id WHERE st.username=? AND d.username=?', 'ss', $roll, $this->actor['user'] ?? '')) { return; }
        throw new \DomainException('You do not have access to this document.');
    }
}
