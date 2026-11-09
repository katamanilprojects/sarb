<?php
declare(strict_types=1);
namespace College;
require_once __DIR__ . '/Clock.php';

final class RequestSecurity
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validToken(array $post, array $session, array $server = []): bool
    {
        foreach ([[$post['_csrf'] ?? $post['csrf_token'] ?? $server['HTTP_X_CSRF_TOKEN'] ?? null, $session['csrf_token'] ?? null],
                  [$post['secretcode'] ?? null, $session['secretcode'] ?? null]] as [$supplied, $expected]) {
            if (is_string($supplied) && is_string($expected) && $expected !== '' && hash_equals($expected, $supplied)) {
                return true;
            }
        }
        return false;
    }

    public static function deny(string $message, int $status = 403): never
    {
        http_response_code($status);
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || str_contains(basename($_SERVER['SCRIPT_FILENAME'] ?? ''), 'ajax')) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 0, 'err' => $message]);
        } else {
            echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        }
        exit;
    }

    /** Add the stable token without changing legacy one-use tokens or form logic. */
    public static function protectForms(string $html): string
    {
        $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');
        return preg_replace_callback('/<form\b[^>]*>/i', static function (array $match) use ($token): string {
            if (!preg_match('/\bmethod\s*=\s*["\']?post\b/i', $match[0])) {
                return $match[0];
            }
            if (preg_match('/\baction\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $match[0], $action)) {
                $url = html_entity_decode($action[1] !== '' ? $action[1] : (($action[2] ?? '') !== '' ? $action[2] : ($action[3] ?? '')), ENT_QUOTES, 'UTF-8');
                if (preg_match('#^(?:https?:)?//#i', $url)) {
                    $parts = parse_url(str_starts_with($url, '//') ? 'https:' . $url : $url);
                    $target = ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
                    if (strcasecmp($target, $_SERVER['HTTP_HOST'] ?? '') !== 0) { return $match[0]; }
                } elseif (preg_match('/^[a-z][a-z0-9+.-]*:/i', $url)) { return $match[0]; }
            }
            return $match[0] . '<input type="hidden" name="_csrf" value="' . $token . '">';
        }, $html) ?? $html;
    }

    public static function boot(string $portal, string $page): void
    {
        Clock::configure();
        if (PHP_SAPI === 'cli') {
            return;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'use_strict_mode' => true]);
        }
        self::token();
        ob_start([self::class, 'protectForms']);
        $public = ['index.php', 'viewcertificate.php', 'managebuildings_public.php', 'public_feedback_status.php', 'viewtimetable.php', 'adminuserindex.php'];
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($page, $public, true)) {
            if (empty($_SESSION['user']) || empty($_SESSION['userid']) || empty($_SESSION['role'])) {
                if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || str_contains(basename($_SERVER['SCRIPT_FILENAME'] ?? ''), 'ajax')) {
                    self::deny('Please sign in to access this page.', 401);
                }
                header('Location: ./');
                exit;
            }
            if ($portal === 'student' && $_SESSION['role'] !== 'student') {
                self::deny('Student access required.');
            }
            if ($portal === 'staff' && $_SESSION['role'] === 'student') {
                self::deny('Staff access required.');
            }
            if ($portal === 'staff') {
                $userRole = $_SESSION['role'] ?? '';
                if ($userRole !== 'superadmin') {
                    $sharedAcademicAdminPages = [
                        'adminfacst.php',
                        'adminviewclasses.php',
                        'adminenrollstudents.php',
                        'adminviewstudents.php',
                        'adminunenrollstudents.php',
                        'adminuploadst.php',
                        'adminstudentprofiles.php',
                        'admincustodyledger.php',
                    ];
                    $sharedStudentPages = [
                        'viewclasses.php',
                        'enrollstudents.php',
                        'viewstudents.php',
                        'unenrollstudents.php',
                        'uploadstudents.php',
                        'editstudent.php',
                    ];
                    $exemptPages = [
                        'faculty_weekly_timetable.php',
                        'academicsectionresults.php',
                        'facciaanalysis2.class.php',
                        'viewtimetable.php',
                        'facusermanual.php',
                        'facusermanual_pdf.php',
                        'hodusermanual.php',
                        'hodusermanual_pdf.php',
                        'adminusermanual.php',
                        'adminusermanual_pdf.php',
                        'academicsectionusermanual.php',
                        'academicsectionusermanual_pdf.php',
                        'superadminusermanual.php',
                        'superadminusermanual_pdf.php',
                    ];

                    if (in_array($page, $sharedStudentPages, true)) {
                        if (!in_array($userRole, ['academic_section', 'admin'], true)) {
                            self::deny('Your role cannot use this page.');
                        }
                    } elseif (!in_array($page, $exemptPages, true)) {
                        if ($userRole === 'academic_section' && in_array($page, $sharedAcademicAdminPages, true)) {
                            // Academic Section has authorized access to shared institutional student enrollment and profile pages
                        } else {
                            foreach (['superadmin' => 'superadmin', 'academicsection' => 'academic_section', 'admin' => 'admin', 'hod' => 'hod', 'fac' => 'faculty'] as $prefix => $requiredRole) {
                                if (str_starts_with($page, $prefix) && $userRole !== $requiredRole) {
                                    self::deny('Your role cannot use this page.');
                                }
                            }
                        }
                    }
                }
            }
        }
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && !self::validToken($_POST, $_SESSION, $_SERVER)) {
            self::deny('The form has expired. Refresh the page and submit again.');
        }
        // Normalise legacy aliases; userid is always the users.id identity.
        if (!empty($_SESSION['userid'])) {
            $_SESSION['user_id'] = (int)$_SESSION['userid'];
        }
        if (!empty($_SESSION['facid'])) {
            $_SESSION['faculty_id'] = (int)$_SESSION['facid'];
        }
    }

    public static function guardResources(\mysqli $db): void
    {
        static $checked = false;
        if ($checked || PHP_SAPI === 'cli' || empty($_SESSION['userid'])) { return; }
        require_once __DIR__ . '/RecordScope.php';
        $scope = new RecordScope($db, $_SESSION);
        $input = array_merge($_GET, $_POST);
        try {
            foreach (['sub_id', 'subject_id'] as $key) {
                if (!empty($input[$key])) {
                    $ids=is_string($input[$key]) && str_contains($input[$key],',')?explode(',',$input[$key]):(array)$input[$key];
                    foreach ($ids as $id) {
                        if (filter_var($id, FILTER_VALIDATE_INT) === false || (int)$id < 1) { throw new \DomainException('Invalid subject identifier.'); }
                        $scope->subject((int)$id);
                    }
                }
            }
            if (!empty($input['curr_sub_id'])) { $scope->curriculumSubject((int)$input['curr_sub_id']); }
            foreach (['class_id', 'cls_id'] as $key) {
                if (!empty($input[$key])) { $scope->classRecord((int)$input[$key]); }
            }
            $isMapping = in_array($input['whattodo'] ?? '', ['addstudents', 'unmapstudents'], true)
                || str_contains(basename($_SERVER['SCRIPT_FILENAME'] ?? ''), 'mapstudent');
            $subject = $isMapping ? null : ($input['sub_id'] ?? $input['subject_id'] ?? null);
            $subject = is_scalar($subject) && (int)$subject > 0 ? (int)$subject : null;
            foreach (['student_id', 'stu_id', 'stu_ids'] as $key) {
                if (!empty($input[$key])) {
                    foreach ((array)$input[$key] as $id) { $scope->student((int)$id, $subject); }
                }
            }
        } catch (\DomainException $e) { self::deny($e->getMessage()); }
        $checked = true;
    }
}
