<?php
declare(strict_types=1);
namespace College;

final class CertificateNumber
{
    public static function reserve(\mysqli $db, string $type): string
    {
        $code=match($type){'CUSTODIAL'=>'CUST','BONAFIDE'=>'BONA','STUDY_CONDUCT'=>'SC','TRANSFER_CERTIFICATE'=>'TC','NO_DUES'=>'ND',default=>throw new \InvalidArgumentException('Unsupported certificate type.')};
        $prefix='JNTUACEA/ACAD/'.date('Y').'/'.$code.'/';$pattern=$prefix.'%';
        // Seed from the largest issued suffix, then reserve under the unique prefix lock.
        // This statement is safe whether the caller has an open transaction or uses autocommit.
        $stmt=$db->prepare("INSERT INTO certificate_sequences(prefix,last_number) SELECT ?,COALESCE(MAX(CAST(SUBSTRING_INDEX(certificate_no,'/',-1) AS UNSIGNED)),0) FROM student_certificate_requests WHERE certificate_no LIKE ? ON DUPLICATE KEY UPDATE last_number=certificate_sequences.last_number");
        $stmt->bind_param('ss',$prefix,$pattern);$stmt->execute();
        $stmt=$db->prepare('UPDATE certificate_sequences SET last_number=LAST_INSERT_ID(last_number+1) WHERE prefix=?');
        $stmt->bind_param('s',$prefix);$stmt->execute();
        $number=(int)$db->query('SELECT LAST_INSERT_ID()')->fetch_row()[0];
        if ($number<1) { throw new \RuntimeException('Unable to reserve a certificate number.'); }
        return $prefix.sprintf('%04d',$number);
    }
}
