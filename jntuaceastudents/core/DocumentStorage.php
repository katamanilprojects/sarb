<?php
declare(strict_types=1);
namespace College;

final class DocumentStorage
{
    public static function root(): string
    {
        // A private location must be configured explicitly. No new upload goes into the web root.
        $root=defined('COLLEGE_DOCUMENT_ROOT')?(string)COLLEGE_DOCUMENT_ROOT:'';
        $resolved=$root!==''?realpath($root):false;
        $web=realpath(dirname(__DIR__,3));
        if (!$resolved || !is_dir($resolved) || !is_writable($resolved) || ($web && ($resolved===$web || str_starts_with($resolved,$web.DIRECTORY_SEPARATOR)))) {
            throw new \RuntimeException('Configure COLLEGE_DOCUMENT_ROOT as a writable private directory outside the web document root before uploading documents.');
        }
        return $resolved;
    }

    public static function resolve(string $stored, string $workspace): ?string
    {
        if (str_starts_with($stored,'private:')) {
            $name=substr($stored,8);
            if (!preg_match('/^[a-f0-9]{64}\.(pdf|jpg|png|webp)$/D',$name)) { return null; }
            $path=self::root().DIRECTORY_SEPARATOR.$name;
            return is_file($path)?$path:null;
        }
        // Read existing documents during migration, within the documented upload directory only.
        if (!preg_match('#^uploads/student_docs/[A-Za-z0-9_.-]+$#D',$stored)) { return null; }
        foreach (['jntuacea','jntuaceastudents'] as $portal) {
            $base=realpath($workspace.'/'.$portal.'/uploads/student_docs');
            $path=realpath($workspace.'/'.$portal.'/'.$stored);
            if ($base && $path && str_starts_with($path,$base.DIRECTORY_SEPARATOR) && is_file($path)) { return $path; }
        }
        return null;
    }
}
