<?php
declare(strict_types=1);

namespace Services;

require_once __DIR__ . '/FeatureManager.php';
require_once __DIR__ . '/SettingsService.php';

/**
 * Class UserManualService
 *
 * Centralized, feature-aware User Manual engine for JNTUA CEA SARB.
 * Dynamically builds role-specific, step-by-step user manuals for:
 *   - faculty
 *   - hod
 *   - admin (Principal / College Administration)
 *   - academic_section
 *   - superadmin
 *
 * Filters sections and steps at runtime based on active Superadmin feature flags
 * (`system_feature_modules`) and granular role action permissions (`role_module_permissions`).
 * Shared by both the interactive Web Manuals (`*usermanual.php`) and
 * the printable/downloadable mPDF generators (`*usermanual_pdf.php`).
 */
class UserManualService
{
    private static ?self $instance = null;
    private FeatureManager $featureManager;

    public function __construct(?FeatureManager $featureManager = null)
    {
        $this->featureManager = $featureManager ?? FeatureManager::getInstance();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Safely resolve regulatory baseline numbers without throwing if a regulation code
     * is shared across multiple programs (e.g. B.Tech R23 and M.Tech R23).
     *
     * @return array<string, float|int>
     */
    public function getRegulatoryDefaults(): array
    {
        $defaults = [
            'min_aggregate_pct'   => 75.0,
            'condone_floor_pct'   => 65.0,
            'min_subject_pct'     => 40.0,
            'better_weight_pct'   => 80,
            'lesser_weight_pct'   => 20,
            'direct_weight_pct'   => 80,
            'indirect_weight_pct' => 20,
            'cia_direct_pct'      => 30,
            'see_direct_pct'      => 70,
            'lab_d2d_max'         => 15.0,
            'lab_int_max'         => 15.0,
            'see_max_marks'       => 70.0,
        ];

        try {
            $settings = SettingsService::getInstance();
            $regParam = 'R23';
            $regsMeta = $settings->getRegulationsWithMetadata();
            if (!empty($regsMeta)) {
                foreach ($regsMeta as $r) {
                    if (strtoupper(trim((string)($r['regulation'] ?? ''))) === 'R23' && !empty($r['id'])) {
                        $regParam = (string)(int)$r['id'];
                        break;
                    }
                }
                if ($regParam === 'R23' && !empty($regsMeta[0]['id'])) {
                    $regParam = (string)(int)$regsMeta[0]['id'];
                }
            }

            $defaults['min_aggregate_pct']   = (float)$settings->get('attendance_min_aggregate_pct', $regParam, 75.0);
            $defaults['condone_floor_pct']   = (float)$settings->get('attendance_condone_floor_pct', $regParam, 65.0);
            $defaults['min_subject_pct']     = (float)$settings->get('attendance_min_subject_pct', $regParam, 40.0);
            $defaults['better_weight_pct']   = (int)round((float)$settings->get('theory_mid_better_weight', $regParam, 0.80) * 100);
            $defaults['lesser_weight_pct']   = (int)round((float)$settings->get('theory_mid_lesser_weight', $regParam, 0.20) * 100);
            $defaults['direct_weight_pct']   = (int)round((float)$settings->get('overall_direct_weight', $regParam, 0.80) * 100);
            $defaults['indirect_weight_pct'] = (int)round((float)$settings->get('overall_indirect_weight', $regParam, 0.20) * 100);
            $defaults['cia_direct_pct']      = (int)round((float)$settings->get('attainment_direct_cia_weight', $regParam, 0.30) * 100);
            $defaults['see_direct_pct']      = (int)round((float)$settings->get('attainment_direct_see_weight', $regParam, 0.70) * 100);
            $defaults['lab_d2d_max']         = (float)$settings->get('lab_cia_day_to_day_marks', $regParam, 15.0);
            $defaults['lab_int_max']         = (float)$settings->get('lab_cia_internal_test_marks', $regParam, 15.0);
            $defaults['see_max_marks']       = (float)$settings->get('theory_see_max_marks', $regParam, 70.0);
        } catch (\Throwable $e) {
            // Keep safe statutory defaults if DB settings lookup fails
        }

        return $defaults;
    }

    /**
     * Check if a module is visible to the target role.
     */
    public function isModuleVisibleForRole(?string $moduleKey, string $role = 'faculty'): bool
    {
        if ($moduleKey === null || $moduleKey === '') {
            return true;
        }

        $r = strtolower(trim($role));
        if ($r === 'superadmin') {
            return true;
        }
        if ($r === 'faculty') {
            return FeatureManager::isFacultyVisible($moduleKey, true)
                && FeatureManager::canAction($moduleKey, 'view', 'faculty');
        }
        if ($r === 'hod') {
            return FeatureManager::isHodVisible($moduleKey, true)
                && FeatureManager::canAction($moduleKey, 'view', 'hod');
        }
        if ($r === 'academic_section') {
            return FeatureManager::isAcademicSectionVisible($moduleKey, true)
                && FeatureManager::canAction($moduleKey, 'view', 'academic_section');
        }
        if ($r === 'admin') {
            return FeatureManager::isAdminVisible($moduleKey, true)
                && FeatureManager::canAction($moduleKey, 'view', 'admin');
        }

        return FeatureManager::canRoleAccess($moduleKey, $r)
            && FeatureManager::canAction($moduleKey, 'view', $r);
    }

    /**
     * Check if a module is in Read-Only mode for the target role.
     */
    public function isModuleReadOnlyForRole(?string $moduleKey, string $role = 'faculty'): bool
    {
        if ($moduleKey === null || $moduleKey === '') {
            return false;
        }

        $r = strtolower(trim($role));
        if ($r === 'superadmin') {
            return false;
        }
        if ($r === 'faculty') {
            return FeatureManager::isFacultyReadOnly($moduleKey)
                || !FeatureManager::canAction($moduleKey, 'edit', 'faculty');
        }
        if ($r === 'hod') {
            return FeatureManager::isHodReadOnly($moduleKey)
                || !FeatureManager::canAction($moduleKey, 'edit', 'hod');
        }
        if ($r === 'academic_section') {
            return FeatureManager::isAcademicSectionReadOnly($moduleKey)
                || !FeatureManager::canAction($moduleKey, 'edit', 'academic_section');
        }
        if ($r === 'admin') {
            return FeatureManager::isAdminReadOnly($moduleKey)
                || !FeatureManager::canAction($moduleKey, 'edit', 'admin');
        }

        return FeatureManager::isRoleReadOnly($moduleKey, $r)
            || !FeatureManager::canAction($moduleKey, 'edit', $r);
    }

    /**
     * Check if export action is allowed for a module and role.
     */
    public function canExport(?string $moduleKey, string $role = 'faculty'): bool
    {
        if ($moduleKey === null || $moduleKey === '') {
            return true;
        }
        $r = strtolower(trim($role));
        if ($r === 'superadmin') {
            return true;
        }
        return FeatureManager::canAction($moduleKey, 'export', $r);
    }

    /**
     * Return human-readable role metadata (title, subtitle, home URL, manual URLs, quick-start steps).
     *
     * @return array<string, mixed>
     */
    public function getRoleMetadata(string $role): array
    {
        $r = strtolower(trim($role));
        return match ($r) {
            'hod' => [
                'role'        => 'hod',
                'role_label'  => 'Head of Department (HOD)',
                'portal_title'=> 'HOD Portal User Manual',
                'subtitle'    => 'Step-by-step departmental guide for managing classes, curriculum subjects, faculty/student mappings, timetables, attendance approvals, and OBE quality audits.',
                'home_url'    => 'hodhome.php',
                'web_url'     => 'hodusermanual.php',
                'pdf_url'     => 'hodusermanual_pdf.php',
                'quick_start' => [
                    ['title' => '1. Start of Semester', 'text' => 'Open **Classes**, add curriculum **Subjects** (and lab groups), and map **Faculty** & **Students**.'],
                    ['title' => '2. Schedule Timetable', 'text' => 'Configure weekly period allocations and rooms for each class section via **Timetable**.'],
                    ['title' => '3. Daily Oversight', 'text' => 'Monitor **Overall Class Attendance**, grant special OD/NCC permissions, and review deletion requests.'],
                    ['title' => '4. Quality & Audits', 'text' => 'Review **Course-wise CIA Analysis**, student feedback, and verify **Course review and action tracking**.'],
                ],
            ],
            'admin' => [
                'role'        => 'admin',
                'role_label'  => 'Principal / Institutional Admin',
                'portal_title'=> 'Principal & Admin User Manual',
                'subtitle'    => 'Executive step-by-step manual for departmental oversight, faculty onboarding, student enrollment, campus attendance & feedback monitoring, and statutory certificates.',
                'home_url'    => 'adminhome.php',
                'web_url'     => 'adminusermanual.php',
                'pdf_url'     => 'adminusermanual_pdf.php',
                'quick_start' => [
                    ['title' => '1. Department Hub', 'text' => 'Manage **HOD Details**, onboard teaching **Faculty**, and oversee class student enrollments under **Departments**.'],
                    ['title' => '2. Attendance & Rooms', 'text' => 'Monitor college-wide **Overall Class Attendance Summary**, Condonation Gazettes, and live campus room usage.'],
                    ['title' => '3. Quality & Surveys', 'text' => 'Track live student **Survey Submission Status** and inspect departmental **CIA Performance & Attainment**.'],
                    ['title' => '4. Exams & Dossiers', 'text' => 'Oversee **Exam Results Overview**, verify **Student Dossier & Profiles**, and manage **Custodial & Certificates Ledger**.'],
                ],
            ],
            'academic_section' => [
                'role'        => 'academic_section',
                'role_label'  => 'Academic Section',
                'portal_title'=> 'Academic Section User Manual',
                'subtitle'    => 'Operational guide for maintaining course structures, master curriculum subjects & COs, student enrollments, campus buildings, examination results, and student certificates.',
                'home_url'    => 'academicsectionhome.php',
                'web_url'     => 'academicsectionusermanual.php',
                'pdf_url'     => 'academicsectionusermanual_pdf.php',
                'quick_start' => [
                    ['title' => '1. Curriculum & BoS', 'text' => 'Maintain **Course Structure**, **Curriculum Subjects**, Master COs/Matrices, and **Syllabus** files.'],
                    ['title' => '2. Student Enrollment', 'text' => 'Bulk-upload or enroll students into department classes and manage campus **Buildings & Halls**.'],
                    ['title' => '3. Exam Results & CIA', 'text' => 'Create exam notifications, preview/import result CSVs, reconcile CIA variances, and publish results under **Publish Results**.'],
                    ['title' => '4. Profiles & Custody', 'text' => 'Verify **Student Profiles & Vault**, record original certificates in safe custody, and issue statutory certificates.'],
                ],
            ],
            'superadmin' => [
                'role'        => 'superadmin',
                'role_label'  => 'SuperAdmin (Institutional Governance)',
                'portal_title'=> 'SuperAdmin Governance & Configuration Manual',
                'subtitle'    => 'Complete reference for academic structure masters, regulations, student cohorts, full-cycle OBE hierarchy, regulatory settings, and system-wide feature toggles.',
                'home_url'    => 'superadminhome.php',
                'web_url'     => 'superadminusermanual.php',
                'pdf_url'     => 'superadminusermanual_pdf.php',
                'quick_start' => [
                    ['title' => '1. Academic Structure', 'text' => 'Configure **Departments**, **Degree Programs**, **Specializations**, and **Academic Years**.'],
                    ['title' => '2. Regulations & Policy', 'text' => 'Define **Academic Regulations**, tune **Active Term Settings**, and set **Attainment Profiles**.'],
                    ['title' => '3. Cohorts & Full OBE', 'text' => 'Create **Student Batches**, bulk-setup classes, and manage Master Vision/Mission/PEO/PO articulation in **Batch OBE**.'],
                    ['title' => '4. Feature Governance', 'text' => 'Control global switches, role visibility (`Visible`/`Read-Only`/`Hidden`), and granular action permissions under **Feature Toggles**.'],
                ],
            ],
            default => [
                'role'        => 'faculty',
                'role_label'  => 'Faculty / Course Instructor',
                'portal_title'=> 'Faculty Portal User Manual',
                'subtitle'    => 'Step-by-step guide tailored automatically to the features and menu options currently enabled for your faculty account.',
                'home_url'    => 'fachome.php',
                'web_url'     => 'facusermanual.php',
                'pdf_url'     => 'facusermanual_pdf.php',
                'quick_start' => [
                    ['title' => '1. Start of Term', 'text' => 'Verify **My Weekly Timetable**, define **Course Outcomes (COs)**, and map the **CO-PO/PSO Articulation Matrix**.'],
                    ['title' => '2. After Each Class', 'text' => 'Record student presence and your **Teaching Diary** via **Mark Attendance** within the active classwork window.'],
                    ['title' => '3. Internal Assessments', 'text' => 'Configure question metadata, enter/upload marks, and submit final scores under **Enter CIA Marks & MetaData**.'],
                    ['title' => '4. End of Semester', 'text' => 'Review **View CIA Analysis & Attainment**, **View Student Feedbacks**, and download your official **e-Bluebook PDF**.'],
                ],
            ],
        };
    }

    /**
     * Build and return the complete, runtime-filtered manual for the given role.
     *
     * @param string $role Role identifier ('faculty', 'hod', 'admin', 'academic_section', 'superadmin')
     * @return array<string, mixed>
     */
    public function getManualForRole(string $role = 'faculty'): array
    {
        $r = strtolower(trim($role));
        if ($r === 'academicsection') {
            $r = 'academic_section';
        }
        $reg = $this->getRegulatoryDefaults();
        $meta = $this->getRoleMetadata($r);

        $rawCatalog = match ($r) {
            'hod'              => $this->buildHodCatalog($reg),
            'admin'            => $this->buildAdminCatalog($reg),
            'academic_section' => $this->buildAcademicSectionCatalog($reg),
            'superadmin'       => $this->buildSuperAdminCatalog($reg),
            default            => $this->buildFacultyCatalog($reg),
        };

        $filteredCategories = [];
        $flatStories = [];
        $activeModules = [];
        $allModulesMeta = $this->featureManager->getAllModules();

        $storyCounter = 1;
        $categoryCounter = 1;

        foreach ($rawCatalog as $cat) {
            $visibleStories = [];

            foreach ($cat['stories'] as $story) {
                $modKey = $story['module_key'] ?? null;
                $extraCheck = $story['enabled_if'] ?? true;

                if (!$extraCheck) {
                    continue;
                }
                if ($modKey !== null && !$this->isModuleVisibleForRole($modKey, $r)) {
                    continue;
                }

                $isReadOnly = $this->isModuleReadOnlyForRole($modKey, $r);
                $canExport  = $this->canExport($modKey, $r);

                $steps = [];
                foreach ($story['steps'] as $step) {
                    if (is_array($step)) {
                        if (!empty($step['requires_export']) && !$canExport) {
                            continue;
                        }
                        if (!empty($step['requires_write']) && $isReadOnly) {
                            continue;
                        }
                        $steps[] = (string)$step['text'];
                    } else {
                        $steps[] = (string)$step;
                    }
                }

                $story['number'] = $storyCounter++;
                $story['category_id'] = $cat['id'];
                $story['category_title'] = $cat['title'];
                $story['category_icon'] = $cat['icon'];
                $story['category_color'] = $cat['color'];
                $story['access_mode'] = $isReadOnly ? 'READONLY' : 'FULL';
                $story['can_export'] = $canExport;
                $story['steps'] = $steps;

                if ($modKey !== null) {
                    $modMeta = $allModulesMeta[$modKey] ?? null;
                    $story['module_name'] = $modMeta['module_name'] ?? $modKey;
                    $activeModules[$modKey] = [
                        'module_key'  => $modKey,
                        'module_name' => $story['module_name'],
                        'access_mode' => $story['access_mode'],
                        'can_export'  => $canExport,
                    ];
                } else {
                    $story['module_name'] = 'System Core';
                }

                if ($isReadOnly) {
                    $story['readonly_notice'] = "This feature ({$story['module_name']}) is currently set to Read-Only mode for {$meta['role_label']}. You can view existing records and permitted reports, but adding, editing, or deleting records is disabled.";
                } else {
                    $story['readonly_notice'] = null;
                }

                $visibleStories[] = $story;
                $flatStories[] = $story;
            }

            if (!empty($visibleStories)) {
                $cat['number'] = $categoryCounter++;
                $cat['Roman']  = $this->toRoman($cat['number']);
                $cat['stories'] = $visibleStories;
                $filteredCategories[] = $cat;
            }
        }

        return [
            'role'             => $r,
            'role_label'       => $meta['role_label'],
            'portal_title'     => $meta['portal_title'],
            'subtitle'         => $meta['subtitle'],
            'home_url'         => $meta['home_url'],
            'web_url'          => $meta['web_url'],
            'pdf_url'          => $meta['pdf_url'],
            'quick_start'      => $meta['quick_start'],
            'generated_at'     => date('d M Y, h:i A'),
            'total_stories'    => count($flatStories),
            'total_categories' => count($filteredCategories),
            'active_modules'   => array_values($activeModules),
            'categories'       => $filteredCategories,
            'stories'          => $flatStories,
            'regulatory'       => $reg,
        ];
    }

    // =========================================================================
    // 1. FACULTY CATALOG
    // =========================================================================
    private function buildFacultyCatalog(array $reg): array
    {
        $showCiaMeta     = $this->isModuleVisibleForRole('MOD_CIA_METADATA', 'faculty');
        $showBloom       = $this->isModuleVisibleForRole('MOD_BLOOM_ANALYSIS', 'faculty');
        $showLabRubrics  = $this->isModuleVisibleForRole('MOD_LAB_RUBRICS', 'faculty');
        $showCourseFile  = $this->isModuleVisibleForRole('MOD_COURSE_MASTER_FILE', 'faculty');
        $showSarFormC3   = $this->isModuleVisibleForRole('MOD_SAR_FORM_C3', 'faculty');
        $showSeeMarks    = $this->isModuleVisibleForRole('MOD_SEE_MARKS', 'faculty');
        $showCiaMarks    = $this->isModuleVisibleForRole('MOD_CIA_MARKS', 'faculty');
        $showDiary       = $this->isModuleVisibleForRole('MOD_CLASS_DIARY', 'faculty');
        $showAiAssist    = $this->isModuleVisibleForRole('MOD_AI_ASSISTANCE', 'faculty');
        $canExportAtt    = $this->canExport('MOD_ATTENDANCE', 'faculty');
        $canExportCia    = $this->canExport('MOD_CIA_MARKS', 'faculty');
        $canExportObe    = $this->canExport('MOD_OBE_ANALYSIS', 'faculty');
        $canExportFb     = $this->canExport('MOD_FEEDBACK', 'faculty');

        $minAgg   = $reg['min_aggregate_pct'];
        $condone  = $reg['condone_floor_pct'];
        $minSub   = $reg['min_subject_pct'];
        $bestWt   = $reg['better_weight_pct'];
        $lessWt   = $reg['lesser_weight_pct'];
        $labD2d   = $reg['lab_d2d_max'];
        $labInt   = $reg['lab_int_max'];
        $seeMax   = $reg['see_max_marks'];

        return [
            [
                'id'          => 'account-and-schedule',
                'title'       => 'Account, Home Dashboard & Weekly Schedule',
                'icon'        => 'bi-person-badge',
                'color'       => 'primary',
                'description' => 'Essential sign-in, security controls, personalized weekly class timetable, and Outcome-Based Education (OBE) quick references.',
                'stories'     => [
                    [
                        'id'            => 'portal-login',
                        'title'         => 'Sign In to the Faculty Portal',
                        'icon'          => 'bi-box-arrow-in-right',
                        'module_key'    => null,
                        'nav_path'      => 'Staff Portal Login Page',
                        'route_url'     => 'fachome.php',
                        'route_label'   => 'Go to Home',
                        'purpose'       => 'Authenticate securely into your faculty account to manage attendance, teaching diaries, assessments, and course files.',
                        'prerequisites' => [
                            'Active faculty account created by the College Administration or Head of Department (HOD).',
                            'Assigned Username and Password.',
                        ],
                        'steps'         => [
                            'Open the JNTUACEA Staff Portal login page in your browser.',
                            'Enter your assigned **Username** and **Password** accurately.',
                            'Click **Login** to start an authenticated session and open the Faculty **Home** dashboard.',
                            'Verify that your name appears in the top-right header (`User: <Your Name>`) along with the main faculty navigation bar.',
                        ],
                        'example'       => 'Dr. A. Sharma signs in with username `asharma` and lands on the Faculty Home Dashboard showing active menu options and reminders.',
                        'validations'   => [
                            'Account Lockout Protection: The login page automatically locks further sign-in attempts for **15 minutes** after **5 consecutive failed attempts**.',
                            'If you forget your password, contact your HOD or Portal Administrator for an audited password reset.',
                        ],
                    ],
                    [
                        'id'            => 'weekly-timetable',
                        'title'         => 'View Your Personal Weekly Timetable',
                        'icon'          => 'bi-calendar-week',
                        'module_key'    => null,
                        'nav_path'      => 'Home → My Weekly Timetable',
                        'route_url'     => 'faculty_weekly_timetable.php',
                        'route_label'   => 'Open My Timetable',
                        'purpose'       => 'Inspect your personalized Monday–Saturday period schedule, assigned classrooms/labs, and live current-period location indicator.',
                        'prerequisites' => [
                            'Your HOD must have mapped you to your subjects and scheduled your class timetable slots.',
                        ],
                        'steps'         => [
                            'From the top navigation bar, click **Home**.',
                            'In the top-right corner of the Instructions card, click **My Weekly Timetable**.',
                            'Review the **Where is ...?** live status banner at the top to see your expected current or upcoming class session.',
                            'Switch between **Grid View** (traditional day-by-period matrix) and **List View** (compact mobile-friendly schedule) using the view toggle buttons.',
                            'Check each scheduled slot for the **Subject Name & Code**, **Class / Section**, and **Building / Hall Name**.',
                        ],
                        'example'       => 'On Monday at 09:30 AM (Period 1), the timetable highlights `DBMS (20A05401T)` for `III B.Tech I Sem CSE - Sec A` in `Lecture Hall Complex - Room 301`.',
                        'validations'   => [
                            'Faculty accounts are strictly scoped to their own schedule and cannot view or edit another instructor’s timetable from this screen.',
                            'If a subject or period is missing from your timetable, ask your HOD to update the class timetable mapping.',
                        ],
                    ],
                    [
                        'id'            => 'obe-knowledge-base',
                        'title'         => 'Consult the OBE Knowledge Base & Bloom’s Taxonomy Guide',
                        'icon'          => 'bi-lightbulb',
                        'module_key'    => null,
                        'nav_path'      => 'Home → Additional Resources → OBE Knowledge Base',
                        'route_url'     => 'facknowledgebase.php',
                        'route_label'   => 'Open Knowledge Base',
                        'purpose'       => 'Reference standardized Outcome-Based Education (OBE) definitions, PEO/PO/PSO/CO hierarchies, and Bloom’s Revised Taxonomy cognitive levels (L1–L6) while framing course outcomes or exam papers.',
                        'prerequisites' => [
                            'Authenticated faculty session.',
                        ],
                        'steps'         => [
                            'On the **Home** page, scroll to the **Additional Resources & User Manuals** section.',
                            'Click **Click here to learn key strategies for successful Outcome-Based Education (OBE)**.',
                            'Review the foundational hierarchy connecting Institutional Vision & Mission, PEOs, POs/PSOs, Course Outcomes (COs), and Bloom’s cognitive levels.',
                            'Use the Bloom’s action verb reference (`Remember`, `Understand`, `Apply`, `Analyze`, `Evaluate`, `Create`) when setting up COs or question paper blueprints.',
                        ],
                        'example'       => 'While preparing Mid-1 questions, an instructor checks the Knowledge Base to select L3 (`Apply`) and L4 (`Analyze`) verbs for problem-solving questions.',
                        'validations'   => [
                            'Read-only reference guide available throughout the semester in a new browser tab.',
                        ],
                    ],
                    [
                        'id'            => 'change-password-logout',
                        'title'         => 'Change Your Password & Log Out Safely',
                        'icon'          => 'bi-key',
                        'module_key'    => null,
                        'nav_path'      => 'Top Menu → Change Password / Logout',
                        'route_url'     => 'facchgpwd.php',
                        'route_label'   => 'Change Password',
                        'purpose'       => 'Keep your faculty credentials secure by updating your password periodically and terminating your session when leaving a computer.',
                        'prerequisites' => [
                            'Knowledge of your existing password.',
                        ],
                        'steps'         => [
                            'Click **Change Password** in the top navigation bar.',
                            'Type your current password in **Enter Existing Password**.',
                            'Type a new password (different from your existing password) in **Enter New Password** and repeat it in **Confirm New Password**.',
                            'Click **Update** and verify the green success alert.',
                            'When finished working—especially on shared staff-room or lab computers—click **Logout** in the top-right menu to clear your session.',
                        ],
                        'example'       => 'After logging in with an initial college-issued password, the faculty member updates it via **Change Password** and immediately sees the confirmation banner.',
                        'validations'   => [
                            'New Password and Confirm New Password must match exactly, and the new password cannot be identical to the current password.',
                            'All password updates are stored using secure cryptographic hashing.',
                        ],
                    ],
                ],
            ],
            [
                'id'          => 'attendance-and-register',
                'title'       => 'Classroom Attendance Marking & Register Views',
                'icon'        => 'bi-clipboard-check',
                'color'       => 'success',
                'description' => 'Daily period attendance recording, teaching diary entry, cumulative/detailed attendance registers, 7-day single-student corrections, and exceptional attendance.',
                'stories'     => [
                    [
                        'id'            => 'mark-daily-attendance',
                        'title'         => 'Mark Class Attendance & Teaching Diary',
                        'icon'          => 'bi-pencil-square',
                        'module_key'    => 'MOD_ATTENDANCE',
                        'nav_path'      => 'Top Menu → Mark Attendance',
                        'route_url'     => 'facaddattendance.php',
                        'route_label'   => 'Mark Attendance',
                        'purpose'       => 'Record hourly student presence/absence and the syllabus topic covered in a single step.',
                        'prerequisites' => [
                            'Subject and enrolled students must be mapped to you by your HOD.',
                            'The selected class date must fall within the active classwork start and end dates configured in the academic calendar.',
                        ],
                        'steps'         => [
                            'Click **Mark Attendance** in the top menu.',
                            'Select your **Subject** (verify subject code, class, section, and academic year) and choose the class **Date** (future dates are blocked).',
                            'Click **Get Students** to load available unmarked periods and the enrolled student roster.',
                            'Enter the syllabus topics taught during the session in the **Teaching Diary** text field.',
                            'Tick the appropriate **Period / Hours** checkbox(es) from the available unmarked hours list. (Select multiple hours only if the exact same attendance and diary apply to a continuous block).',
                            'Review the student list: use **Select/Unselect All Students** to mark everyone present, then untick only the students who were absent.',
                            'Verify the live **Number of Students Present** counter and tick the confirmation checkbox.',
                            ['text' => 'Click **Save Attendance** once and wait for the confirmation alert, then verify the record under **Show Attendance**.', 'requires_write' => true],
                        ],
                        'example'       => 'For 60 enrolled students in `DBMS (20A05401T)`, Dr. Sharma selects Date `14-10-2026`, ticks Period 1, enters `"BCNF decomposition and lossless join properties"`, unticks 3 absentees (confirming 57 present), and clicks **Save Attendance**.',
                        'validations'   => [
                            'Periods already marked for that subject and date are automatically excluded from the Period / Hours list to prevent duplicate marking.',
                            'Attendance marking is automatically disabled once the classwork end date has passed.',
                            'Both student attendance and the teaching diary entry are saved together automatically.',
                        ],
                    ],
                    [
                        'id'            => 'view-overall-attendance',
                        'title'         => 'View Overall Attendance & Shortage Summary',
                        'icon'          => 'bi-calendar-check',
                        'module_key'    => 'MOD_ATTENDANCE',
                        'nav_path'      => 'Top Menu → Show Attendance → View Overall Attendance',
                        'route_url'     => 'facshowattendance.php',
                        'route_label'   => 'Show Attendance',
                        'purpose'       => 'Review each student’s cumulative attendance percentage in your course and identify students in regulatory condonation or detention shortage brackets.',
                        'prerequisites' => [
                            'At least one attendance session recorded for the selected subject.',
                        ],
                        'steps'         => [
                            'Click **Show Attendance** in the top menu.',
                            'Select your **Subject** from the dropdown.',
                            'Leave **Select Date Range? : No** to inspect the entire semester, or choose **Yes** and select a **Start Date** and **End Date**.',
                            'Click the blue **View Overall Attendance** button.',
                            "Inspect the student roster showing **Adm. No.**, **Student Name**, and **Overall Percentage**, followed by the automated shortage summary table grouping roll numbers into **{$condone}% – {$minAgg}%** (condonation zone), **< {$condone}%** (detention zone), and **< {$minSub}%** (individual subject minimum shortage).",
                        ],
                        'example'       => "Selecting `DBMS` and clicking **View Overall Attendance** lists all 60 students and highlights roll numbers falling between {$condone}% and {$minAgg}% or below {$condone}%.",
                        'validations'   => [
                            'Inactive students are clearly badged as `Inactive` so they are distinguished from active cohort members.',
                        ],
                    ],
                    [
                        'id'            => 'view-detailed-attendance-bluebook',
                        'title'         => 'View Detailed Period Register, Diary, CIA & Download e-Bluebook',
                        'icon'          => 'bi-journal-richtext',
                        'module_key'    => 'MOD_ATTENDANCE',
                        'nav_path'      => 'Top Menu → Show Attendance → Detailed Attendance & e-Bluebook',
                        'route_url'     => 'facshowattendance.php',
                        'route_label'   => 'Open Register & Exports',
                        'purpose'       => 'Inspect the period-by-period Present (`P`) / Absent (`A`) matrix, review chronological diary logs or CIA tables, and export the statutory university e-Bluebook PDF or CSV register.',
                        'prerequisites' => [
                            'Recorded attendance sessions for the selected subject.',
                        ],
                        'steps'         => array_values(array_filter([
                            'Open **Show Attendance**, select your **Subject**, and optionally set a **Date Range**.',
                            'Click **Detailed Attendance & e-Bluebook** to display the full date-and-hour attendance grid alongside each student’s **Total Classes Held**, **Classes Present**, and **Percentage**.',
                            $canExportAtt ? ['text' => 'Below the detailed grid, click **Download Detailed Attendance** to export a CSV spreadsheet, or click **Download e-Bluebook PDF** to generate the multi-page institutional e-Bluebook.', 'requires_export' => true] : null,
                            $showDiary ? 'To inspect only your chronological lecture notes (Date, Time, and Diary text), click the **Show Diary** button on the Show Attendance form.' : null,
                            $showCiaMarks ? ('To inspect the consolidated internal marks register and uploaded CIA attachments, click **Show Continuous Internal Assessment**' . ($canExportCia ? ' (and use **Download as Excel** to export the sheet).' : '.')) : null,
                        ])),
                        'example'       => 'At the end of the month, the instructor opens **Detailed Attendance & e-Bluebook** and clicks **Download e-Bluebook PDF** to obtain the formatted course file containing the cover page, attendance grid, cohort summary, lesson plan, class diary, compliance audit, and CIA register.',
                        'validations'   => [
                            'The Detailed Attendance CSV respects your selected Start/End Date filter, while the official e-Bluebook compiles the full statutory course record.',
                        ],
                    ],
                    [
                        'id'            => 'update-single-student-attendance',
                        'title'         => 'Correct a Single Student’s Attendance Status (7-Day Rule)',
                        'icon'          => 'bi-arrow-repeat',
                        'module_key'    => 'MOD_ATTENDANCE',
                        'nav_path'      => 'Top Menu → Other Options → Class Diary & Attendance Utilities → Update Single Student Attendance',
                        'route_url'     => 'facupdatestudentattendance.php',
                        'route_label'   => 'Update Single Attendance',
                        'purpose'       => 'Rectify an individual student’s attendance mark (Present to Absent or Absent to Present) for a session conducted within the last 7 calendar days.',
                        'prerequisites' => [
                            'The attendance session must already be recorded.',
                            'The session date must be today or within the **last 7 days**.',
                        ],
                        'steps'         => [
                            'Go to **Other Options** and click **Update Single Student Attendance**.',
                            'Select the **Subject** and the class **Date** (within the last 7 days), then click **Get Hours**.',
                            'Select the recorded **Hour**, then click **Get Students**.',
                            'Select the **Student** by roll number/name and click **Get Attendance** to view their current status (`Present` or `Absent`).',
                            ['text' => 'Select the corrected status in **Change Status To**, enter a clear **Reason for correction**, and click **Update Attendance**.', 'requires_write' => true],
                            'Verify the updated status in **Show Attendance**.',
                        ],
                        'example'       => 'Roll No `21021A0514` was present in Period 2 yesterday but accidentally left unticked. The instructor selects the date, Period 2, and student, sets **Change Status To: Present**, enters reason `"Student was present in class; unchecked in error"`, and clicks **Update Attendance**.',
                        'validations'   => [
                            'Strict 7-Day Window: Dates older than 7 calendar days are rejected by the system. For older corrections, contact your HOD.',
                            'Every single-student status change and its written reason are permanently recorded in the audit log.',
                        ],
                    ],
                    [
                        'id'            => 'exceptional-attendance',
                        'title'         => 'Mark Exceptional Attendance for Unmarked / Late-Joining Students',
                        'icon'          => 'bi-exclamation-circle',
                        'module_key'    => 'MOD_ATTENDANCE',
                        'nav_path'      => 'Top Menu → Other Options → Class Diary & Attendance Utilities → Mark Attendance (For Exceptional Cases)',
                        'route_url'     => 'facexceptionalattendance.php',
                        'route_label'   => 'Exceptional Attendance',
                        'purpose'       => 'Add `Present` attendance records for students who had no attendance entry when a past session was originally recorded (such as lateral-entry students or late subject enrollments).',
                        'prerequisites' => [
                            'The class session must already be marked for the rest of the class.',
                            'The student must now be mapped to the subject roster and have **no record** (neither Present nor Absent) for that specific date and hour.',
                        ],
                        'steps'         => [
                            'Open **Other Options** → click **Mark Attendance (For Exceptional Cases)**.',
                            'Select the **Subject** and the **Date** of the previously conducted session, then click **Get Marked Hours**.',
                            'Choose the recorded **Hour** and click **Get Unmarked Students**.',
                            'Review the list of students who currently have no attendance entry for that session and tick those whose presence/credit is confirmed.',
                            ['text' => 'Verify the selected student count, tick the confirmation checkbox, and click **Mark Selected Students as Present**.', 'requires_write' => true],
                        ],
                        'example'       => 'After a lateral-entry batch is mapped in Week 3, the instructor opens **Mark Attendance (For Exceptional Cases)** to add present entries for confirmed orientation sessions where those students had no prior attendance record.',
                        'validations'   => [
                            'Important Distinction: This tool only lists students with **no existing attendance record** for that hour. If a student was already marked `Absent` and needs to be changed to `Present`, use **Update Single Student Attendance** instead.',
                        ],
                    ],
                ],
            ],
            [
                'id'          => 'class-diary-utilities',
                'title'       => 'Standalone Class Diary Logging',
                'icon'        => 'bi-journal-text',
                'color'       => 'info',
                'description' => 'Record non-instructional events, suspended classes, or special academic activities in the teaching diary without altering student attendance counts.',
                'stories'     => [
                    [
                        'id'            => 'add-diary-only',
                        'title'         => 'Add Diary Only (Without Student Attendance)',
                        'icon'          => 'bi-calendar-plus',
                        'module_key'    => 'MOD_CLASS_DIARY',
                        'nav_path'      => 'Top Menu → Other Options → Class Diary & Attendance Utilities → Add Diary Only (No Attendance)',
                        'route_url'     => 'facadddairy.php',
                        'route_label'   => 'Add Diary Only',
                        'purpose'       => 'Log a diary entry for a scheduled timetable period when regular attendance should not be marked (e.g., classes suspended for a college event, sudden holiday, or department symposium).',
                        'prerequisites' => [
                            'Subject must be assigned to you.',
                        ],
                        'steps'         => [
                            'Navigate to **Other Options** → click **Add Diary Only (No Attendance)**.',
                            'Select your **Subject** and the **Date**, then click **Get Timings**.',
                            'Enter the reason or activity description in the diary text box and tick the relevant period(s).',
                            ['text' => 'Tick the confirmation checkbox **I accept, Only Diary is Added Without Student Attendance** and click **Save Dairy**.', 'requires_write' => true],
                            'Verify the saved note under **Show Attendance → Show Diary**.',
                        ],
                        'example'       => 'When afternoon classes are suspended for the College Annual Day, the instructor logs `"Classes suspended per Principal circular for Annual Day"` under Period 6 via **Add Diary Only**.',
                        'validations'   => [
                            'Saves a teaching diary note only; it does not add Present or Absent entries and does not change student attendance percentages.',
                        ],
                    ],
                ],
            ],
            [
                'id'          => 'attendance-deletion-requests',
                'title'       => 'Attendance Session Deletion Requests (HOD Approval)',
                'icon'        => 'bi-envelope-exclamation',
                'color'       => 'warning',
                'description' => 'Audited workflow for requesting the Head of Department (HOD) to delete an attendance session that was marked for the wrong subject, date, or hour.',
                'stories'     => [
                    [
                        'id'            => 'submit-track-deletion-request',
                        'title'         => 'Submit & Track an Attendance Deletion Request',
                        'icon'          => 'bi-send',
                        'module_key'    => 'MOD_ATT_REQUESTS',
                        'nav_path'      => 'Top Menu → Other Options → Attendance Deletion Requests → Submit New Deletion Request / View Submitted Requests',
                        'route_url'     => 'facadddelattrequest.php',
                        'route_label'   => 'Submit Deletion Request',
                        'purpose'       => 'Request formal HOD approval to remove an entire erroneously marked class session (both attendance and diary) so you can re-enter it accurately.',
                        'prerequisites' => [
                            'An existing attendance session recorded by you that needs to be completely removed.',
                        ],
                        'steps'         => [
                            'Go to **Other Options** → **Attendance Deletion Requests** and click **Submit New Deletion Request**.',
                            'Select the **Subject** and **Date**, then click **Get Hours**.',
                            'Select the erroneous **Hour** and click **Get Details** to preview the recorded session summary.',
                            ['text' => 'Enter a clear, specific **Reason for Delete** (e.g., *"Marked under Section B instead of Section A in error"*) and click **Submit Request**.', 'requires_write' => true],
                            'To check whether the HOD has acted on your request, open **Other Options → View Submitted Requests** and inspect the status badge (**Pending**, **Approved**, or **Rejected**).',
                            'Once the status shows **Approved**, confirm in **Show Attendance** that the session was removed before marking the replacement session.',
                        ],
                        'example'       => 'Dr. Sharma accidentally marks Period 3 attendance on the wrong date, submits a deletion request, tracks it under **View Submitted Requests** until the HOD approves it, and then marks the correct date.',
                        'validations'   => [
                            'Submitting a request does not delete the session immediately; the session remains intact until the HOD approves the request.',
                        ],
                    ],
                ],
            ],
            [
                'id'          => 'electives-and-grouped-subjects',
                'title'       => 'Electives & Shared Multi-Section Utilities',
                'icon'        => 'bi-bookmarks',
                'color'       => 'info',
                'description' => 'Time-saving utilities for instructors teaching a common subject (such as an Open Elective) across multiple sections simultaneously.',
                'stories'     => [
                    [
                        'id'            => 'mark-grouped-attendance',
                        'title'         => 'Mark Combined Attendance for Grouped Sessions',
                        'icon'          => 'bi-people',
                        'module_key'    => 'MOD_GROUPED_ATT',
                        'nav_path'      => 'Top Menu → Other Options → Electives & Shared Subjects Utilities → Mark Attendance (For Grouped Sessions)',
                        'route_url'     => 'facaddgroupedattendance.php',
                        'route_label'   => 'Mark Grouped Attendance',
                        'purpose'       => 'Mark attendance and teaching diary in one screen when multiple sections attend a combined lecture for the same subject code.',
                        'prerequisites' => [
                            'You must be assigned to multiple offerings sharing the **same subject code**.',
                        ],
                        'steps'         => [
                            'Open **Other Options** → click **Mark Attendance (For Grouped Sessions)**.',
                            'Select the multiple assigned offerings with the **same subject code** (hold `Ctrl` on Windows or `Command` on Mac to select multiple rows) and choose the class **Date**.',
                            'Click **Get Combined Student List** to load the merged student roster across all selected sections.',
                            'Enter the **Teaching Diary** topic and select the applicable **Period / Hours**.',
                            ['text' => 'Tick the present students, verify the total present count, tick the confirmation checkbox, and click **Save Grouped Attendance**.', 'requires_write' => true],
                            'Verify the saved attendance under **Show Attendance** for each participating section.',
                        ],
                        'example'       => 'An Open Elective taught simultaneously to CSE Sec A and CSE Sec B is marked in one step using **Mark Attendance (For Grouped Sessions)**.',
                        'validations'   => [
                            'All selected offerings must share the same subject code and compatible period timings.',
                        ],
                    ],
                    [
                        'id'            => 'import-grouped-cia-data',
                        'title'         => 'Copy CIA Question Metadata & Attachments to Parallel Sections',
                        'icon'          => 'bi-arrows-collapse',
                        'module_key'    => 'MOD_GROUPED_ATT',
                        'nav_path'      => 'Top Menu → Other Options → Electives & Shared Subjects Utilities → Import Grouped CIA Data',
                        'route_url'     => 'facgroupedcia.php',
                        'route_label'   => 'Import Grouped CIA Setup',
                        'purpose'       => 'Copy configured CIA question paper metadata (components, questions, Bloom levels, CO mappings) and uploaded CIA attachments from one configured section to parallel sections of the same subject.',
                        'prerequisites' => [
                            'CIA question paper metadata and/or attachments must already be configured in the **Source Subject**.',
                            'Target sections must have the same subject code.',
                        ],
                        'steps'         => [
                            'Open **Other Options** → click **Import Grouped CIA Data**.',
                            'Select the already-configured **Source Subject** and click **Next: Select Targets**.',
                            'Select one or more **Target Subjects** sharing the same subject code, choose **CIA 1** or **CIA 2**, and click **Next: Check for Missing Data**.',
                            'Review the checklist of missing component question metadata and attachment files detected for the target sections.',
                            ['text' => 'Keep the items you wish to copy ticked, click **Import Selected Items**, and confirm the prompt.', 'requires_write' => true],
                            'Open each target section in **Enter CIA Marks & MetaData** to enter student marks for that section.',
                        ],
                        'example'       => 'After setting up Mid-1 question structure and uploading the question paper PDF in Section A, the instructor copies that setup to Section B in 3 clicks via **Import Grouped CIA Data**.',
                        'validations'   => [
                            'Note: This tool copies **missing CIA setup metadata and attachments only**; student marks are never copied across sections and must be entered separately for each section’s roster.',
                        ],
                    ],
                ],
            ],
            [
                'id'          => 'curriculum-outcomes-and-planning',
                'title'       => 'Curriculum Outcomes (CO-PO/PSO) & Lesson Planning',
                'icon'        => 'bi-diagram-3',
                'color'       => 'primary',
                'description' => 'Define or import Course Outcomes (COs), map the CO-PO/PSO Articulation Matrix, plan semester lectures, and reconcile delivery for NBA Criterion 2.2.',
                'stories'     => [
                    [
                        'id'            => 'manage-course-outcomes',
                        'title'         => 'Import, Add, or Edit Course Outcomes (COs)',
                        'icon'          => 'bi-file-earmark-plus',
                        'module_key'    => 'MOD_CO_PO',
                        'nav_path'      => 'Top Menu → Other Options → Curriculum Outcomes & Planning → Add / View Course Outcomes (COs)',
                        'route_url'     => 'facaddcos.php',
                        'route_label'   => 'Manage COs',
                        'purpose'       => 'Establish the measurable Course Outcomes (COs), Bloom’s Taxonomy Levels (BTL), and target thresholds required before configuring CIA/SEE question papers, lesson plans, and student CO feedback.',
                        'prerequisites' => [
                            'Subject must be assigned to you.',
                        ],
                        'steps'         => [
                            'Navigate to **Other Options** → click **Add / View Course Outcomes (COs)**.',
                            'Select your **Subject** and click **Get COs**.',
                            ['text' => '**To Import Approved Master COs**: If curriculum master outcomes exist, click **Import Course Outcomes & Matrix** (or **Import as Editable Subject COs** if shown as inherited) to populate approved syllabus outcomes automatically.', 'requires_write' => true],
                            ['text' => '**To Add COs Manually**: Enter the number of COs defined in the syllabus, click **Generate Fields**, fill each **CO Description**, select its **Bloom’s Taxonomy Level (BTL)**, verify the **Target Threshold Limit (%)**, and click **Save All COs** (or use **Click here to Add New/Missing CO**).', 'requires_write' => true],
                            ['text' => '**To Edit an Existing CO**: Click the **Edit** button beside the CO row, update the text or Bloom level, and click **Update CO**.', 'requires_write' => true],
                        ],
                        'example'       => 'Dr. Sharma loads `DBMS (20A05401T)`, imports 5 curriculum COs (`CO1` to `CO5`) tagged from `Understand (L2)` to `Evaluate (L5)`, and verifies the list.',
                        'validations'   => [
                            'Course Outcomes are a mandatory prerequisite for adding detailed CIA/SEE question metadata, lesson plans, and activating meaningful student CO feedback.',
                        ],
                    ],
                    [
                        'id'            => 'co-po-articulation-matrix',
                        'title'         => 'Configure the CO-PO / PSO Articulation Matrix',
                        'icon'          => 'bi-table',
                        'module_key'    => 'MOD_CO_PO',
                        'nav_path'      => 'Top Menu → Other Options → Curriculum Outcomes & Planning → Add / View CO-PO/PSO Articulation Matrix',
                        'route_url'     => 'facarticulationmatrix.php',
                        'route_label'   => 'Open Articulation Matrix',
                        'purpose'       => 'Map each Course Outcome (CO) to Program Outcomes (PO1–PO12) and Program Specific Outcomes (PSOs) using correlation weights (1 = Low, 2 = Medium, 3 = High).',
                        'prerequisites' => [
                            'Course Outcomes must already be saved for the subject under **Add / View Course Outcomes (COs)**.',
                            'Department POs and PSOs must be configured.',
                        ],
                        'steps'         => [
                            'Go to **Other Options** → click **Add / View CO-PO/PSO Articulation Matrix**.',
                            'Select your **Subject** and click **Add / View Matrix**.',
                            ['text' => 'In the matrix grid, enter correlation strengths where a CO contributes to a PO/PSO: **1** (Low), **2** (Medium), or **3** (High). Leave unrelated cells blank.', 'requires_write' => true],
                            ['text' => 'Click **Add Mappings**, confirm the prompt, and reload the matrix to verify the saved correlation values.', 'requires_write' => true],
                        ],
                        'example'       => 'Mapping `CO2` (Database Design) to `PO1` with `3`, `PO2` with `3`, `PO3` with `3`, and `PSO1` with `2`.',
                        'validations'   => [
                            'Only valid correlation weights (`1`, `2`, `3`, or empty) are accepted. Saved mappings feed directly into the PO/PSO attainment calculations.',
                        ],
                    ],
                    [
                        'id'            => 'lesson-plan-management',
                        'title'         => 'Create, Bulk CSV Upload, Edit, or Clear Your Lesson Plan',
                        'icon'          => 'bi-calendar3',
                        'module_key'    => 'MOD_LESSON_PLAN',
                        'nav_path'      => 'Top Menu → Other Options → Curriculum Outcomes & Planning → Add / View Lesson Plan (Course Delivery Plan)',
                        'route_url'     => 'facaddlessonplan.php',
                        'route_label'   => 'Manage Lesson Plan',
                        'purpose'       => 'Prepare your lecture-by-lecture Course Delivery Plan mapped to Units, COs, Bloom levels, and teaching pedagogies prior to or during the semester.',
                        'prerequisites' => [
                            'Course Outcomes must be defined for the subject under **Add / View Course Outcomes (COs)**.',
                        ],
                        'steps'         => [
                            'Open **Other Options** → click **Add / View Lesson Plan (Course Delivery Plan)**.',
                            'Select your course and click **Load Plan**.',
                            ['text' => '**Option A — Bulk Excel / CSV Import (Recommended)**: Click **Download CSV Template**, fill in your lectures in Excel keeping the template column headers intact, save as `.csv`, choose the file under **Upload Completed CSV**, and click **Upload & Populate Plan**.', 'requires_write' => true],
                            ['text' => '**Option B — Single Lecture Entry**: Enter **Unit #**, unique **Lecture #**, **Target Course Outcome**, **Planned Syllabus Topic / Concept**, **Bloom’s Level**, **Teaching Pedagogy**, and optional **Reference Material**, then click **Save Lecture Plan**.', 'requires_write' => true],
                            ['text' => '**To Edit, Delete, or Clear**: Use the row **Edit Lecture** or **Delete Lecture** icons to adjust individual lectures, or **Clear All Lectures** if you need to replace the entire plan before audit submission.', 'requires_write' => true],
                        ],
                        'example'       => 'Uploading a 45-lecture CSV schedule for `DBMS` populates Units 1–5 with CO mappings and pedagogies in seconds.',
                        'validations'   => [
                            'Once the end-of-semester Course Completion Audit is submitted/approved by the HOD, lesson plan modifications are locked to preserve audit integrity.',
                        ],
                    ],
                    [
                        'id'            => 'lesson-plan-reconciliation',
                        'title'         => 'Reconcile Teaching Diary with Lesson Plan & Submit Course Completion Audit (NBA Crit 2.2)',
                        'icon'          => 'bi-clipboard-check',
                        'module_key'    => 'MOD_LESSON_PLAN',
                        'nav_path'      => 'Top Menu → Other Options → Curriculum Outcomes & Planning → Course Delivery Audit & Reconciliation (NBA Crit 2.2)',
                        'route_url'     => 'faclessonplanreconciliation.php',
                        'route_label'   => 'Open Delivery Audit',
                        'purpose'       => 'Match your actual daily teaching diary entries to planned lectures, record unit completion dates and schedule deviations, and submit your end-of-course compliance sign-off to the HOD.',
                        'prerequisites' => [
                            'Planned lectures saved in your **Lesson Plan** and conducted class diary entries recorded via **Mark Attendance**.',
                        ],
                        'steps'         => array_values(array_filter([
                            'Open **Other Options** → click **Course Delivery Audit & Reconciliation (NBA Crit 2.2)**, select your course, and click **Load Reconciliation**.',
                            'Review the summary cards (**Planned Lectures**, **Conducted Periods**, **Syllabus Coverage %**, **Compensatory / Extra**) and any **Uncovered Planned Lectures** alert.',
                            ['text' => 'In **Step 1 (Diary to Lesson Plan Reconciliation)**: Click **1-Click Auto-Sequence** to map diary entries in sequential order (or manually select the matching planned lecture / **Extra / Compensatory / Revision Class** beside each diary row), then click **Save Mappings**.', 'requires_write' => true],
                            ['text' => 'In **Step 2 (Course Completion Sign-Off)**: Verify or enter the **Unit 1–5 Completion Dates**, document **Reasons for Schedule Deviations**, **Compensatory / Remedial Measures Taken**, and **Content Beyond Syllabus**.', 'requires_write' => true],
                            ['text' => 'Tick the certification statement checkbox and click **Save Draft** (to continue later) or **Submit Course Completion to HOD** (for final HOD verification).', 'requires_write' => true],
                            $showCourseFile ? 'Use the top header buttons to download the **e-Bluebook** or the extended **Master Course File**.' : 'Use the top header button to download the **e-Bluebook**.',
                        ])),
                        'example'       => 'At semester end, Dr. Sharma clicks **1-Click Auto-Sequence**, marks 3 extra problem-solving hours as `Compensatory`, enters unit completion dates, and clicks **Submit Course Completion to HOD**.',
                        'validations'   => [
                            'The certification checkbox is required for both saving a draft and submitting to the HOD.',
                        ],
                    ],
                ],
            ],
            [
                'id'          => 'cia-assessments',
                'title'       => 'Continuous Internal Assessments (CIA) & Question Metadata',
                'icon'        => 'bi-clipboard2-data',
                'color'       => 'success',
                'description' => 'Configure internal assessment question papers, enter question-wise or overall marks across Theory, Lab, DTI, PG, and Project courses, and manage assessment attachments.',
                'stories'     => [
                    [
                        'id'            => 'cia-hub-overview',
                        'title'         => 'Open the CIA Hub & Check Assessment Status',
                        'icon'          => 'bi-layers',
                        'module_key'    => 'MOD_CIA_MARKS',
                        'nav_path'      => 'Top Menu → Other Options → Assessments & Analysis → Enter CIA Marks & MetaData',
                        'route_url'     => 'facciamarks.php',
                        'route_label'   => 'Open CIA Hub',
                        'purpose'       => 'Select your assigned course to view the submission status (`Added` vs `Not Added`) for Internal Assessment 1 & 2 and launch the appropriate entry workflow for Theory, Lab, DTI, PG, or Project subjects.',
                        'prerequisites' => [
                            'Subject must be assigned to you.',
                            'Course Outcomes (COs) must be defined under **Add / View Course Outcomes (COs)** before using **Add/View Detailed**.',
                        ],
                        'steps'         => [
                            'Navigate to **Other Options** → click **Enter CIA Marks & MetaData**.',
                            'Select your **Subject** from the dropdown and click **Get Continuous Assessment Status**.',
                            'Review the status table for **Internal Assessment 1** and **Internal Assessment 2** (or single internal assessment for Lab/DTI/Project):',
                            'Use **Add/View Detailed** for question-by-question marks entry that powers OBE CO/Bloom analysis and auto-calculates component totals.',
                            'Use **Add Overall** / **Add** when entering direct component totals.',
                            'Use **Attachments** to upload question papers, evaluation schemes, and sample scripts.',
                        ],
                        'example'       => 'Selecting `DBMS (20A05401T)` displays rows for Internal Assessment 1 and 2 with buttons for **Add/View Detailed**, **Add Overall**, and **Attachments**.',
                        'validations'   => [
                            'When detailed student marks are entered for all components, the system automatically computes component totals so you can review and submit them without manual addition.',
                        ],
                    ],
                    [
                        'id'            => 'cia-question-blueprint',
                        'title'         => 'Configure CIA Question Paper Metadata, Bloom Levels & CO Mappings',
                        'icon'          => 'bi-list-columns-reverse',
                        'module_key'    => 'MOD_CIA_MARKS',
                        'enabled_if'    => $showCiaMeta,
                        'nav_path'      => 'Other Options → Enter CIA Marks & MetaData → Add/View Detailed → Add Questions',
                        'route_url'     => 'facciamarks.php',
                        'route_label'   => 'Configure CIA Questions',
                        'purpose'       => 'Define the question structure, maximum marks, question choice type, Bloom’s Taxonomy cognitive level, and Course Outcome (CO) mappings for each CIA component (`Subjective`, `Objective`, `Assignment`, `Activity`).',
                        'prerequisites' => [
                            'Course Outcomes (COs) must already be saved for the subject under **Add / View Course Outcomes (COs)** (if COs are inherited from the master catalog without subject copies, import/save them there first).',
                        ],
                        'steps'         => array_values(array_filter([
                            'In **Enter CIA Marks & MetaData**, select your subject and click **Add/View Detailed** for the target assessment.',
                            ['text' => 'If your course requires more than one assignment or activity, click **Click here Add New Assignment** (or **Click here Add New Activity** for DTI) in the card footer.', 'requires_write' => true],
                            ['text' => 'Beside a component (`Subjective`, `Objective`, `Assignment`), click **Add Questions**.', 'requires_write' => true],
                            ['text' => 'For `Subjective` or `Internal Exam` components, select the question paper template from the dropdown and choose subpart splits (**Single**, **a & b**, or **a & b & c**) for each question, then click **Next: Add Question MetaData**.', 'requires_write' => true],
                            ['text' => 'In the metadata table, verify each question’s **Marks**, select its **Type** (Compulsory or Choice) and **Bloom’s Level**, and tick at least one mapped **Course Outcome (CO)** checkbox per row.', 'requires_write' => true],
                            ['text' => 'Click **Save Question MetaData**, then click **View Questions** to verify the blueprint or proceed via **Next Step: Marks Entry**.', 'requires_write' => true],
                        ])),
                        'example'       => 'In Mid-1 Subjective, the instructor selects the 3-question template, splits Q1 into `1a` and `1b` (5 marks each, L2, CO1), maps Q2 (10 marks, L3, CO2) and Q3 (10 marks, L3, CO2), and clicks **Save Question MetaData**.',
                        'validations'   => array_values(array_filter([
                            'Subpart marks within a question must sum to the intended question maximum.',
                            'Once student marks are entered for a component, **Delete Meta Data** on the **View Questions** screen is locked until those student marks are deleted first under **View Marks**.',
                            $showBloom ? 'Bloom’s Cognitive Advisory: Displays Higher-Order Thinking Skills (L4–L6) vs. Lower-Order Thinking Skills (L1–L3) balance guidance on assessment screens.' : null,
                        ])),
                    ],
                    [
                        'id'            => 'cia-detailed-marks-entry',
                        'title'         => 'Enter, CSV Upload, Edit, or Delete Question-wise CIA Marks',
                        'icon'          => 'bi-grid-3x3',
                        'module_key'    => 'MOD_CIA_MARKS',
                        'enabled_if'    => $showCiaMeta,
                        'nav_path'      => 'Other Options → Enter CIA Marks & MetaData → Add/View Detailed → Marks Entry / View Marks',
                        'route_url'     => 'facciamarks.php',
                        'route_label'   => 'Enter Detailed Marks',
                        'purpose'       => 'Record question-by-question student scores via the web grid or CSV template so the platform can compute both CIA component totals and direct Course Outcome attainment.',
                        'prerequisites' => [
                            'Question paper metadata configured for the component under **Add Questions**.',
                        ],
                        'steps'         => [
                            'On the **Add/View Detailed** component screen, click **Marks Entry** beside the configured component.',
                            ['text' => '**Option A — Web Grid Entry**: Enter each student’s score for every question column within the printed maximum (supports 0.5 increments), then click **Save Marks**.', 'requires_write' => true],
                            ['text' => '**Option B — CSV Template Upload**: Click **Download CSV Template**, fill in the evaluated scores in Excel without altering student admission numbers or question headers, save as `.csv`, choose the file, and click **Upload CSV**.', 'requires_write' => true],
                            'After saving, inspect the **View Marks** table to verify every student’s question scores and total.',
                            ['text' => '**To Correct or Delete Marks Before Final Submission**: On the **View Marks** screen, click **Edit Marks** to adjust individual cells and click **Save Marks**, or click **Delete Marks** if you need to clear all scores for that component and re-upload.', 'requires_write' => true],
                        ],
                        'example'       => 'After evaluating Mid-1 scripts, the instructor downloads the CSV template from **Marks Entry**, enters question scores for 60 students, uploads the CSV, and verifies the totals under **View Marks**.',
                        'validations'   => [
                            'Important CSV Rule: Blank cells in the detailed CIA CSV upload are treated as `0`. Upload only verified marks and inspect the **View Marks** table before final submission.',
                            'Once the overall CIA assessment is finalized (**Submit Final Marks**), editing or deleting detailed marks/metadata for that assessment is locked.',
                        ],
                    ],
                    [
                        'id'            => 'cia-condensed-and-overall-submit',
                        'title'         => 'Submit Final CIA Marks (Condensed Review or Direct Overall Entry)',
                        'icon'          => 'bi-check2-square',
                        'module_key'    => 'MOD_CIA_MARKS',
                        'nav_path'      => 'Other Options → Enter CIA Marks & MetaData → View & Submit CIA Marks / Add Overall',
                        'route_url'     => 'facciamarks.php',
                        'route_label'   => 'Review & Submit CIA',
                        'purpose'       => 'Finalize internal assessment marks either by reviewing and submitting the totals auto-calculated from detailed question marks or by entering overall component totals directly.',
                        'prerequisites' => [
                            'For Condensed Submission: Detailed marks must be entered for all components of that assessment (`Subjective`, `Objective`, `Assignment`).',
                        ],
                        'steps'         => [
                            '**Path 1 — From Detailed Question Marks (Recommended)**: On the **Add/View Detailed** screen, once all components have marks entered, click the blue **View & Submit CIA Marks** button. Review each student’s scaled Subjective, Objective, Assignment, and Total CIA columns, then click **Submit Final Marks**.',
                            ['text' => '**Path 2 — Direct Overall Entry**: In **Enter CIA Marks & MetaData**, click **Add Overall**, enter each student’s `Assignment`, `Objective`, and `Subjective` totals within the displayed maximums, and click **Save without Submitting** (to save a temporary draft) or **Validate & Submit Marks** (to finalize and lock).', 'requires_write' => true],
                            "Once both Internal Assessment 1 and 2 are submitted, the system calculates Final CIA using the regulatory formula (**{$bestWt}% of Better Mid + {$lessWt}% of Lesser Mid**).",
                        ],
                        'example'       => "A student scoring `24` in CIA-1 and `28` in CIA-2 receives `0.{$bestWt} × 28 + 0.{$lessWt} × 24 = 27.2`, rounded to `27` in the final CIA register.",
                        'validations'   => [
                            'Saving detailed component marks alone does not finalize the assessment; you must click **Submit Final Marks** on the condensed review screen (or **Validate & Submit Marks** on the overall screen) so the assessment status changes from `Not Added` to `Added`.',
                        ],
                    ],
                    [
                        'id'            => 'cia-laboratory-and-rubrics',
                        'title'         => 'UG Laboratory Marks Entry, Rubrics Assistant & Date-wise Day-to-Day',
                        'icon'          => 'bi-cpu',
                        'module_key'    => 'MOD_CIA_MARKS',
                        'nav_path'      => 'Other Options → Enter CIA Marks & MetaData → (Select Lab Subject) → Add/View Detailed or Add',
                        'route_url'     => 'facciamarks.php',
                        'route_label'   => 'Open Lab CIA',
                        'purpose'       => 'Record continuous Day-to-Day practical evaluation and Internal Lab Examination scores for UG laboratory courses, using either date-wise lab sessions or overall entry with the interactive Rubrics Assistant.',
                        'prerequisites' => [
                            'Laboratory subject assigned to you.',
                        ],
                        'steps'         => array_values(array_filter([
                            'In **Enter CIA Marks & MetaData**, select your Laboratory subject and click **Get Continuous Assessment Status**.',
                            ['text' => '**Method A — Overall Lab Entry' . ($showLabRubrics ? ' & Rubrics Assistant' : '') . '**: Click **Add** under the `Overall` column. Enter each student’s **Day-to-Day** (Max ' . $labD2d . ') and **Internal Test** (Max ' . $labInt . ') marks.' . ($showLabRubrics ? ' Click the calculator icon (or **Rubrics Assistant** button) beside a student’s Day-to-Day field to score individual criteria (`Conduction & Execution`, `Observation & Record`, `Viva-Voce`) in the modal and click **Apply to Student Score**.' : '') . ' Click **Save without Submitting** (draft) or **Validate & Submit Marks** (final).', 'requires_write' => true],
                            ['text' => '**Method B — Date-wise Detailed Lab Entry**: Click **Add/View Detailed**. Click **Click here to Add New Day-to-Day Component**, pick a lab session date within the semester window, and enter scores per date (or use **Bulk Day-to-Day Marks Entry** / CSV upload after the course end date). Enter **Internal Exam** question marks, then open **View & Submit CIA Marks** and click **Submit Final Marks**.', 'requires_write' => true],
                        ])),
                        'example'       => "In `DBMS Lab (20A05401P)`, the instructor uses the **Rubrics Assistant** or date-wise **Day-to-Day** components to finalize scores out of " . ($labD2d + $labInt) . " marks.",
                        'validations'   => [
                            'Both Day-to-Day and Internal Test columns must fall within the configured regulation maxima (`0` to `' . $labD2d . '` and `0` to `' . $labInt . '`).',
                        ],
                    ],
                    [
                        'id'            => 'cia-dti-pg-project',
                        'title'         => 'Design Thinking (DTI), Postgraduate (PG) & UG Project CIA Workflows',
                        'icon'          => 'bi-mortarboard',
                        'module_key'    => 'MOD_CIA_MARKS',
                        'nav_path'      => 'Other Options → Enter CIA Marks & MetaData → (Select DTI / PG / Project Subject)',
                        'route_url'     => 'facciamarks.php',
                        'route_label'   => 'Open Special CIA',
                        'purpose'       => 'Record internal marks for specialized course types: Design Thinking & Innovation (DTI), Postgraduate (PG) courses, and UG Capstone Projects.',
                        'prerequisites' => [
                            'Assigned DTI, PG, or Project course.',
                        ],
                        'steps'         => [
                            '**Design Thinking & Innovation (DTI)**: In **Enter CIA Marks & MetaData**, use **Add/View Detailed** to add activities (**Click here Add New Activity**) and test components (`Subjective-1`, `Objective-1`, `Subjective-2`, `Objective-2`), then submit via **View & Submit CIA Marks**—or enter overall **Day-to-Day / Activity** and **Internal Test** marks directly via **Add Overall**.',
                            '**Postgraduate (PG) Courses**: Select the PG subject in **Enter CIA Marks & MetaData** and click **Add** beside the required component. Use **Save draft** if some students are not yet assessed (blanks allowed in draft), or **Validate & submit marks** when all students have scores. For post-submission corrections, open **View / correct → Correct marks**, enter a mandatory **Reason for correction**, and click **Validate & update marks**.',
                            '**UG Project Courses**: Select the project subject, click **Add**, enter **Supervisor** and **P. R. C.** (Project Review Committee) marks within the printed maxima, and click **Save without Submitting** or **Validate & Submit Marks**.',
                        ],
                        'example'       => 'For a B.Tech Project course, the guide enters Supervisor (30M) and P.R.C. (30M) scores and submits the 60-mark internal total.',
                        'validations'   => [
                            'PG CIA corrections automatically capture a permanent before/after audit record along with the faculty member’s written reason.',
                        ],
                    ],
                    [
                        'id'            => 'cia-attachments',
                        'title'         => 'Upload & Manage CIA Supporting Attachments',
                        'icon'          => 'bi-paperclip',
                        'module_key'    => 'MOD_CIA_MARKS',
                        'enabled_if'    => $showCiaMeta,
                        'nav_path'      => 'Other Options → Enter CIA Marks & MetaData → Attachments',
                        'route_url'     => 'facciamarks.php',
                        'route_label'   => 'Manage Attachments',
                        'purpose'       => 'Upload official question papers, evaluation schemes, and sample answer scripts for each internal assessment and download system-generated component mark CSVs.',
                        'prerequisites' => [
                            'Files in `.pdf`, `.doc`, `.docx`, `.xls`, `.xlsx`, `.jpg`, `.png`, or `.csv` format under **20 MB**.',
                        ],
                        'steps'         => [
                            'In **Enter CIA Marks & MetaData**, select your subject, click **Get Continuous Assessment Status**, and click **Attachments** on the target assessment row.',
                            'Download the reference sample templates in the card footer if needed (**Sample Record of Evaluation in Excel** / **Sample Format of Question Paper in Word**).',
                            ['text' => 'Click **Add New Attachment**, enter a clear **Title of the File** (e.g., `"CIA 1 Question Paper & Scheme"`), select your file under **Attachment**, and click **Submit**.', 'requires_write' => true],
                            'Verify your uploaded file in the **Uploaded Attachments** table using the **Download** button (and download system-generated question-mark CSVs listed below it).',
                            ['text' => 'To replace an erroneous upload, click the red **X** delete button beside that file, confirm the prompt, and upload the replacement.', 'requires_write' => true],
                        ],
                        'example'       => 'Uploading `DBMS_Mid1_QP.pdf` and `DBMS_Mid1_Key.pdf` under Internal Assessment 1 so they are available for accreditation audits.',
                        'validations'   => [
                            'Up to **5 attachments** can be uploaded per assessment, each up to **20 MB**.',
                        ],
                    ],
                ],
            ],
            [
                'id'          => 'see-and-external-results',
                'title'       => 'Semester End Examination (SEE) & External Attainment',
                'icon'        => 'bi-journal-check',
                'color'       => 'primary',
                'description' => 'Configure external question paper blueprints, record or auto-sync SEE scores across Modes A, B, and C, import attainment evidence, and generate optional AI drafts.',
                'stories'     => [
                    [
                        'id'            => 'see-evaluation-modes',
                        'title'         => 'Configure SEE Blueprint & Record External Marks (Modes A, B & C)',
                        'icon'          => 'bi-award',
                        'module_key'    => 'MOD_SEE_MARKS',
                        'nav_path'      => 'Top Menu → Other Options → Assessments & Analysis → Enter SEE Marks & MetaData',
                        'route_url'     => 'facseemarks.php',
                        'route_label'   => 'Open SEE Manager',
                        'purpose'       => 'Configure the external question paper structure and populate Semester End Examination (SEE) marks using Detailed Question Entry (Mode A), Direct Ledger Totals (Mode B), or 1-Click Auto-Sync from Published University Results (Mode C).',
                        'prerequisites' => [
                            'Course Outcomes (COs) must be defined for the subject under **Add / View Course Outcomes (COs)**.',
                        ],
                        'steps'         => [
                            'Open **Other Options** → click **Enter SEE Marks & MetaData**, select your course, and click **Load SEE Status**.',
                            ['text' => '**Step 1 — Configure QP Metadata**: Click **Add Structure** (or **QP Structure**). Select subpart splits for Question 1 (`1a–1j` compulsory short questions) and Questions `2–11` (5 Either/Or choice groups: `Q2 OR Q3`, `Q4 OR Q5`, `Q6 OR Q7`, `Q8 OR Q9`, `Q10 OR Q11`), assign Marks, Bloom’s Levels, and COs, and click **Save SEE Question Paper Metadata**.', 'requires_write' => true],
                            ['text' => '**Mode A — Detailed Question Entry**: Click **Question Entry**, enter sub-question scores via the grid or CSV template (leave unattempted choice alternatives blank; enter `0` for an assessed zero), click **Save & Proceed to Review**, verify choice resolution in **Condensed Review**, and click **Finalize reviewed marks**.', 'requires_write' => true],
                            ['text' => "**Mode B — Direct University Ledger Totals**: Click **Direct Marks**, enter each student’s finalized external total (out of {$seeMax}), and click **Submit University Ledger Scores**.", 'requires_write' => true],
                            ['text' => '**Mode C — 1-Click Auto-Sync from Published Results**: In the Mode C card on **Enter SEE Marks & MetaData**, enter the **Published notification ID** (for a published `REGULAR` exam notification), tick **Replace existing marks** with a reason only if overwriting prior records, and click **Auto-Sync Results**.', 'requires_write' => true],
                        ],
                        'example'       => 'Once the Academic Section publishes regular semester results, the instructor enters the Notification ID in Mode C on **Enter SEE Marks & MetaData** and clicks **Auto-Sync Results** to ingest external scores without manual typing.',
                        'validations'   => [
                            'Mode A provides **Measured** question-level CO attainment evidence; Mode B and Mode C provide **Estimated** CO attainment based on QP CO weights or grade profiles.',
                            'Mode C Auto-Sync accepts published `REGULAR` examination notifications only (supplementary and revaluation notifications are excluded from baseline cohort sync).',
                        ],
                    ],
                    [
                        'id'            => 'see-attainment-import',
                        'title'         => 'Import External Results CSV for Attainment Evidence',
                        'icon'          => 'bi-file-earmark-arrow-up',
                        'module_key'    => 'MOD_SEE_MARKS',
                        'nav_path'      => 'Other Options → Enter SEE Marks & MetaData → Import results for attainment',
                        'route_url'     => 'attainmentimport.php',
                        'route_label'   => 'Import Attainment CSV',
                        'purpose'       => 'Import external marks, grades, or question-wise external scores from an offline CSV file with full preview and validation before committing to attainment calculations.',
                        'prerequisites' => [
                            'Course COs and SEE structure configured.',
                        ],
                        'steps'         => [
                            'From **Enter SEE Marks & MetaData**, click **Import results for attainment** at the top of the page.',
                            'Select your **Course**, choose the **Evidence** mode (`Aggregate marks / grades` or a question-wise component), and click **Load**.',
                            'Click **Download course template**, populate it with student results (keeping column headers intact), and save as `.csv` (up to 10 MB).',
                            ['text' => 'Select the CSV file and click **Preview import** to inspect valid rows, skipped subject codes, and any validation errors.', 'requires_write' => true],
                            ['text' => 'If replacing existing marks, tick **Replace existing marks for these students** and enter the mandatory reason, then click **Confirm reviewed import**.', 'requires_write' => true],
                        ],
                        'example'       => 'Uploading a verified result broadsheet CSV under **Import results for attainment**, reviewing the 60-row preview table, and clicking **Confirm reviewed import**.',
                        'validations'   => [
                            'Importing attainment evidence here updates course attainment analysis only; it does not publish official student grade cards on the Student Portal.',
                        ],
                    ],
                    [
                        'id'            => 'optional-ai-assistance',
                        'title'         => 'Generate & Review Optional AI Drafts for Attainment / CQI',
                        'icon'          => 'bi-stars',
                        'module_key'    => 'MOD_AI_ASSISTANCE',
                        'enabled_if'    => $showAiAssist,
                        'nav_path'      => 'Other Options → Enter SEE Marks & MetaData → Optional AI drafts',
                        'route_url'     => 'aiassistance.php',
                        'route_label'   => 'Open AI Drafts',
                        'purpose'       => 'Use an institutionally approved AI provider on an opt-in basis to generate draft attainment observations or CQI action proposals from verified course metrics.',
                        'prerequisites' => [
                            'Both SEE Marks and Optional AI Assistance must be enabled, with an active provider configured by the institution.',
                        ],
                        'steps'         => [
                            'From **Enter SEE Marks & MetaData**, click **Optional AI drafts**.',
                            ['text' => 'Tick the opt-in consent checkbox for the task type (`ATTAINMENT_ANALYSIS` or `ATR_PROPOSAL`) and click **Save preference**.', 'requires_write' => true],
                            'Select your **Course** and click **Review source** to inspect the exact deterministic metrics that will be summarized.',
                            ['text' => 'Choose the task type and approved **Provider**, tick the confirmation checkbox, and click **Queue draft**.', 'requires_write' => true],
                            'Review the generated draft under **Your drafts** and verify all figures against your course records before using them.',
                        ],
                        'example'       => 'Queueing an `ATR_PROPOSAL` draft for a course outcome with an attainment gap and reviewing the suggested remedial plan.',
                        'validations'   => [
                            'AI drafts are optional human-reviewed suggestions; all official marks, attainment levels, and CQI records remain 100% deterministic.',
                        ],
                    ],
                ],
            ],
            [
                'id'          => 'obe-analytics-and-feedback',
                'title'       => 'OBE Attainment Analytics, Diagnostics & Student Feedback',
                'icon'        => 'bi-graph-up-arrow',
                'color'       => 'success',
                'description' => 'Interactive CO/PO/PSO attainment dashboards, Bloom’s cognitive charts, Zero-Marks diagnostics, NBA/NAAC PDF dossiers, and 3-tier student feedback reports.',
                'stories'     => [
                    [
                        'id'            => 'obe-attainment-dashboard',
                        'title'         => 'Analyze Course Outcome (CO/PO) Attainment, Bloom’s & Zero-Marks Diagnostics',
                        'icon'          => 'bi-pie-chart',
                        'module_key'    => 'MOD_OBE_ANALYSIS',
                        'nav_path'      => 'Top Menu → Other Options → Assessments & Analysis → View CIA Analysis & Attainment',
                        'route_url'     => 'facciaanalysis2.php',
                        'route_label'   => 'Open Attainment Analysis',
                        'purpose'       => 'Inspect interactive charts and tables for Assessment Performance, CO/PO Attainment, Bloom’s Taxonomy levels, Question Difficulty, and Zero-Marks Executive Diagnostics, and export official accreditation PDFs.',
                        'prerequisites' => [
                            'Detailed question-wise CIA marks (and/or SEE marks) saved and mapped to Course Outcomes.',
                        ],
                        'steps'         => array_values(array_filter([
                            'Navigate to **Other Options** → click **View CIA Analysis & Attainment**.',
                            'Select your **Subject** (or choose **★ All Batches Combined** if you teach multiple batches of the same course) and select the **Assessment** scope (`CIA 1`, `CIA 2`, `Overall CIA`, `Lab CIA`' . ($showSeeMarks ? ', or `Semester End Exam (SEE)`' : '') . '), then click **Load Analysis**.',
                            'Explore the analysis tabs:',
                            '• **Overview**: Assessment Performance Snapshot & Component-wise Performance.',
                            '• **Outcome Analysis (CO/PO)**: CO Attainment vs. Target threshold and mapped PO Attainment.',
                            '• **Cognitive Level (Bloom’s)**: Performance distribution across L1–L6 cognitive levels.',
                            '• **CO-PO Matrix**: Detailed table linking CO attainment percentages to PO/PSO weights.',
                            $showSeeMarks ? '• **Direct & Overall Attainment (NBA/NAAC)**: Combines Direct Attainment (CIA + SEE) and Indirect Attainment (Student CO Survey) on the 0.0–3.0 scale.' : null,
                            '• **Other Insights**: Student-wise CO Spread, Question Difficulty Overview, and the **Zero Marks Analysis** executive diagnostic (question-wise recorded zeros and distinct students impacted per CO).',
                            $canExportObe ? ('In the **Course Documents & Reports** footer, select an individual subject offering and click **OBE Analytics Dossier**' . ($showSarFormC3 ? ', **NBA SAR Form C-3**,' : '') . ' or **e-Bluebook Course File**.') : null,
                        ])),
                        'example'       => 'Loading `DBMS` → `Overall CIA` under **View CIA Analysis & Attainment** to inspect which COs met the 60% target benchmark and check `Other Insights → Zero Marks Analysis` to identify questions with high recorded-zero counts.',
                        'validations'   => [
                            'When downloading subject PDF dossiers (**OBE Analytics Dossier**, **NBA SAR Form C-3**, **e-Bluebook**), select the specific individual subject offering rather than the combined-batch view.',
                        ],
                    ],
                    [
                        'id'            => 'student-feedback-reports',
                        'title'         => 'View Student Feedback Reports & Faculty Self-Appraisal Summary',
                        'icon'          => 'bi-chat-dots',
                        'module_key'    => 'MOD_FEEDBACK',
                        'nav_path'      => 'Top Menu → Other Options → Assessments & Analysis → View Student Feedbacks',
                        'route_url'     => 'facviewfeedback.php',
                        'route_label'   => 'View Student Feedback',
                        'purpose'       => 'Review anonymous student feedback across Course Outcomes (CO Survey), Course End Survey (CES), and Faculty Teaching Appraisal, and export PDF/Excel/CSV reports.',
                        'prerequisites' => [
                            'Students must have submitted feedback for your course during the active feedback window.',
                        ],
                        'steps'         => array_values(array_filter([
                            'Navigate to **Other Options** → click **View Student Feedbacks**.',
                            '**For a Single Course (`Subject-wise Feedback`)**: Set **Feedback Report Type** to **Subject-wise Feedback**, select the **Academic Year** and **Select Assigned Subject**, and click **View Feedback Report**.',
                            'Inspect response turnout counts, Course Outcome indirect ratings, Course End Survey (CES) distributions, teaching criteria scores, and qualitative student suggestions.',
                            '**For Multi-Course / Multi-Year Summary (`Faculty Self-Appraisal`)**: Set **Feedback Report Type** to **Faculty Self-Appraisal (Comprehensive)**, select an Academic Year (or all years), and click **Refresh Self-Appraisal**.',
                            $canExportFb ? 'Use the **Export PDF**, **Export Excel**, or **Export CSV** buttons to save offline copies for your course file or appraisal dossier.' : null,
                        ])),
                        'example'       => 'Reviewing the `Subject-wise Feedback` report for `DBMS` (55/60 responses, 91.7% turnout) and exporting the enhanced PDF report.',
                        'validations'   => [
                            'Strict Student Anonymity: All student feedback ratings and qualitative remarks are aggregated and anonymized; individual student identities are never exposed to faculty.',
                        ],
                    ],
                ],
            ],
            [
                'id'          => 'cqi-and-action-tracking',
                'title'       => 'Continuous Quality Improvement (CQI) & Action Tracking',
                'icon'        => 'bi-clipboard-data',
                'color'       => 'warning',
                'description' => 'Propose remedial interventions for Course Outcomes with attainment gaps, track HOD review states, record execution evidence, and download Action Taken Reports (ATR).',
                'stories'     => [
                    [
                        'id'            => 'cqi-workflow-and-atr',
                        'title'         => 'Propose CQI Remedial Actions, Update Review Queue & Download ATR',
                        'icon'          => 'bi-arrow-repeat',
                        'module_key'    => 'MOD_CQI_ACTION_TRACKING',
                        'nav_path'      => 'Home → Course review and action tracking & Course Delivery Audit & Reconciliation',
                        'route_url'     => 'reviewqueue.php',
                        'route_label'   => 'Open CQI Review Queue',
                        'purpose'       => 'Close the OBE quality loop (NBA Criterion 3.3.2 / NAAC Metric 2.6.2) by proposing remedial actions for unattained Course Outcomes, responding to HOD reviews, logging execution evidence, and exporting the signed Action Taken Report (ATR).',
                        'prerequisites' => [
                            'Saved CIA/OBE attainment evidence and configured CO targets for the subject.',
                        ],
                        'steps'         => [
                            ['text' => '**Step 1 — Propose a CQI Action**: Open **Other Options → Course Delivery Audit & Reconciliation**, load your course, scroll to **Log New CQI Remedial Action**, select the **Course Outcome** and **Action Category** (`Remedial Classes`, `Pedagogical Change`, `Tutorial Hours`, `Assignment Revision`, or `Content Beyond Syllabus`), enter the **Remedial Action Narrative & Methodology**, and click **Record CQI Remedial Action**.', 'requires_write' => true],
                            'From the **Home** page, click the top button **Course review and action tracking**.',
                            ['text' => '**Step 2 — Respond to HOD Review**: Locate your action card in the review queue. If the HOD marked it `REVISION_REQUESTED`, select **REVISED**, enter your updated narrative in **Observations / execution evidence**, and click **Save decision**.', 'requires_write' => true],
                            ['text' => '**Step 3 — Record Completion Evidence**: Once the action is `APPROVED` by the HOD and you have carried out the intervention, select **COMPLETED**, document the concrete execution evidence (dates, attendance, quiz improvement), and click **Save decision**.', 'requires_write' => true],
                            'Click **Download Action Taken Report** on the action card to generate the official PDF report.',
                        ],
                        'example'       => 'After identifying a gap in `CO4`, the instructor logs a remedial tutorial plan, executes two weekend practice sessions after HOD approval, marks the item `COMPLETED` in **Course review and action tracking**, and downloads the ATR PDF.',
                        'validations'   => [
                            'Lifecycle Governance: Faculty transition actions through `PENDING` → `REVISED` / `COMPLETED`; final verification and `CLOSED` status are executed by the HOD.',
                        ],
                    ],
                ],
            ],
        ];
    }

    // =========================================================================
    // 2. HOD CATALOG
    // =========================================================================
    private function buildHodCatalog(array $reg): array
    {
        $showCourseFile = $this->isModuleVisibleForRole('MOD_COURSE_MASTER_FILE', 'hod');
        $showGazette    = $this->isModuleVisibleForRole('MOD_ATTENDANCE_CONDONATION_GAZETTE', 'hod');
        $showSarFormC3  = $this->isModuleVisibleForRole('MOD_SAR_FORM_C3', 'hod');
        $showCqi        = $this->isModuleVisibleForRole('MOD_CQI_ACTION_TRACKING', 'hod');
        $showLessonPlan = $this->isModuleVisibleForRole('MOD_LESSON_PLAN', 'hod');
        $showSeeMarks   = $this->isModuleVisibleForRole('MOD_SEE_MARKS', 'hod');
        $showAiAssist   = $this->isModuleVisibleForRole('MOD_AI_ASSISTANCE', 'hod');

        $minAgg  = $reg['min_aggregate_pct'];
        $condone = $reg['condone_floor_pct'];
        $minSub  = $reg['min_subject_pct'];

        return [
            [
                'id'          => 'hod-dashboard-and-timetables',
                'title'       => 'Department Dashboard, Campus Schedules & Security',
                'icon'        => 'bi-person-workspace',
                'color'       => 'primary',
                'description' => 'HOD portal authentication, quick action shortcuts, faculty/campus timetable monitoring, and account password security.',
                'stories'     => [
                    [
                        'id'            => 'hod-login-dashboard',
                        'title'         => 'Sign In to the HOD Portal & Navigate Quick Actions',
                        'icon'          => 'bi-house-door',
                        'module_key'    => null,
                        'nav_path'      => 'Staff Portal Login → HOD Home',
                        'route_url'     => 'hodhome.php',
                        'route_label'   => 'Open HOD Home',
                        'purpose'       => 'Access your department’s operational control center to manage classes, subjects, faculty/student allocations, timetables, attendance records, and quality assurance queues.',
                        'prerequisites' => [
                            'Active Department HOD account assigned to your department.',
                        ],
                        'steps'         => [
                            'Sign in at the JNTUACEA Staff Portal using your HOD credentials.',
                            'On **HOD Home**, review the top navigation bar (**Home**, **Classes**, **Faculty**, **Academic Records**, **Change Password**, **Logout**) and header shortcuts.',
                            'Use **Faculty Weekly Timetable** to inspect the weekly teaching schedule of any faculty member in your department.',
                            'Use **Campus Timetable** to inspect live period-by-period classroom and laboratory occupancy across buildings.',
                        ],
                        'example'       => 'The HOD of CSE logs in, clicks **Faculty Weekly Timetable**, and selects an instructor to verify their current lecture room and weekly teaching load.',
                        'validations'   => [
                            'All HOD operations are automatically scoped to your assigned department.',
                        ],
                    ],
                    [
                        'id'            => 'hod-password-logout',
                        'title'         => 'Change HOD Password & Log Out Safely',
                        'icon'          => 'bi-key',
                        'module_key'    => null,
                        'nav_path'      => 'Top Menu → Change Password / Logout',
                        'route_url'     => 'hodchgpwd.php',
                        'route_label'   => 'Change Password',
                        'purpose'       => 'Maintain security over departmental administration by updating your HOD password regularly and logging out after each session.',
                        'prerequisites' => [
                            'Current HOD password.',
                        ],
                        'steps'         => [
                            'Click **Change Password** in the top navigation bar.',
                            'Enter your existing password, type a new strong password, confirm it, and click **Update**.',
                            'Always click **Logout** when leaving your workstation.',
                        ],
                        'example'       => 'Updating the departmental HOD password at the start of a new semester.',
                        'validations'   => [
                            'New password must match confirmation and differ from the old password.',
                        ],
                    ],
                ],
            ],
            [
                'id'          => 'hod-classes-subjects-timetables',
                'title'       => 'Classes, Curriculum Subjects, Mappings & Class Timetables',
                'icon'        => 'bi-journal-bookmark',
                'color'       => 'success',
                'description' => 'End-to-end start-of-semester setup: adding curriculum subjects (including multi-batch labs), syncing master COs/matrices, mapping faculty and students, and building class timetables.',
                'stories'     => [
                    [
                        'id'            => 'hod-browse-classes',
                        'title'         => 'Browse Department Specializations & Academic Year Classes',
                        'icon'          => 'bi-building',
                        'module_key'    => null,
                        'nav_path'      => 'Top Menu → Classes',
                        'route_url'     => 'hodviewclasses.php',
                        'route_label'   => 'Open Classes',
                        'purpose'       => 'View all active and historical class cohorts grouped by specialization and academic year, and launch Subject, Faculty, Student, or Timetable management for any class.',
                        'prerequisites' => [
                            'Classes created by SuperAdmin for your department’s specializations.',
                        ],
                        'steps'         => [
                            'Click **Classes** in the top menu.',
                            'Locate your **Specialization** card (e.g., `B.Tech - Computer Science & Engineering`) and expand the **Academic Year** accordion (active academic years are highlighted in green).',
                            'Inspect each class row’s **Start** and **End** dates and use the 4 action buttons on the right: **Subjects**, **Faculty**, **Students** (shown once students are enrolled in the class), and **Timetable**.',
                        ],
                        'example'       => 'Expanding `Academic Year: 2026-2027` under CSE to configure subjects, faculty, students, and timetable for `III B.Tech I Sem CSE - Sec A`.',
                        'validations'   => [
                            'If a class displays `Enrollment Pending` instead of the **Students** button, ask the Academic Section or Admin to enroll/upload the student roster for that class first.',
                            'Timetable scheduling (**Timetable** button) is available immediately even before student enrollment is completed.',
                        ],
                    ],
                    [
                        'id'            => 'hod-manage-class-subjects',
                        'title'         => 'Add Subjects from Curriculum, Generate Lab Batches & Sync Master COs',
                        'icon'          => 'bi-book',
                        'module_key'    => null,
                        'nav_path'      => 'Top Menu → Classes → Click "Subjects" on a Class Row',
                        'route_url'     => 'hodviewclasses.php',
                        'route_label'   => 'Manage Class Subjects',
                        'purpose'       => 'Add standardized syllabus subjects to a class from the Academic Section’s Curriculum Catalog, auto-generate multi-batch lab groups (`Group - A`, `Group - B`, etc.), and synchronize Master Course Outcomes and Articulation Matrices.',
                        'prerequisites' => [
                            'Curriculum subjects must be defined by the Academic Section for that regulation, specialization, and semester.',
                        ],
                        'steps'         => array_values(array_filter([
                            'In **Classes**, click **Subjects** on the target class row.',
                            'In the **Add Subject to Class** card, select a course from **1. Select Course / Subject** (courses already added to the class are marked with `✓ [Already Added]`).',
                            'In **2. Batches / Groups**, keep `1 Batch - Whole Class (Default)` for regular theory courses, or select `2 Batches (Group A, Group B)`, `3 Batches`, or `4 Batches` for laboratory/practical courses split into student groups.',
                            'Click **Add Subject to Class** (or **Add N Batches to Class**).',
                            'In the **Class Subjects** table header below, click **Sync Missing COs & Matrix from Curriculum** to copy any approved Master Course Outcomes and CO-PO/PSO mappings from the curriculum catalog into your class offerings.',
                            $showCourseFile ? 'Click the bookmark icon (**Download Course Master File**) beside any subject row to export its accreditation Course Master File PDF.' : null,
                        ])),
                        'example'       => 'Selecting `DBMS Lab (20A05401P)` with `2 Batches` automatically creates `DBMS Lab (Group - A)` and `DBMS Lab (Group - B)` with matching curriculum codes so separate lab instructors and student batches can be mapped.',
                        'validations'   => [
                            'A subject cannot be deleted once attendance records have been marked against it.',
                        ],
                    ],
                    [
                        'id'            => 'hod-map-faculty-and-students',
                        'title'         => 'Map Faculty & Enrolled Students to Class Subjects',
                        'icon'          => 'bi-people-fill',
                        'module_key'    => null,
                        'nav_path'      => 'Top Menu → Classes → Click "Faculty" & "Students" on a Class Row',
                        'route_url'     => 'hodviewclasses.php',
                        'route_label'   => 'Map Faculty & Students',
                        'purpose'       => 'Assign course instructors (from your department or servicing departments) and map enrolled students (whole class, lab batches, or elective subsets) to each subject offering.',
                        'prerequisites' => [
                            'Subjects must be added to the class under **Subjects**.',
                            'For student mapping: Students must be enrolled in the class.',
                        ],
                        'steps'         => [
                            '**1. Map Faculty**: In **Classes**, click **Faculty** on the class row. Select the **Subject**, choose the **Department** (defaults to your department, or pick another department for servicing/interdisciplinary courses) and **Faculty Member**, and click **Add Faculty**. Use the **Unmap** button in the mapped table below to remove an incorrect assignment.',
                            '**2. Map Students**: In **Classes**, click **Students** on the class row. Select the **Subject** (e.g., a core subject, `Group - A` lab batch, or an Open Elective).',
                            'Tick the students from the **Unmapped Students** list who belong to that subject/batch (use **Select All** for whole-class core courses, or tick specific roll number ranges for lab groups/electives) and click **Map Selected Students**.',
                            'To remove students from a subject or lab group, tick them in the **Mapped Students** table and click **Unmap Selected Students**.',
                        ],
                        'example'       => 'Mapping Roll Numbers `21021A0501`–`21021A0530` to `DBMS Lab (Group - A)` and `21021A0531`–`21021A0560` to `DBMS Lab (Group - B)`.',
                        'validations'   => [
                            'Faculty can only see a subject in their portal after they are mapped under **Faculty**, and students only appear on the attendance/marks roster after being mapped under **Students**.',
                        ],
                    ],
                    [
                        'id'            => 'hod-manage-class-timetable',
                        'title'         => 'Create & Manage Class Weekly Timetable',
                        'icon'          => 'bi-calendar-week',
                        'module_key'    => null,
                        'nav_path'      => 'Top Menu → Classes → Click "Timetable" on a Class Row',
                        'route_url'     => 'hodviewclasses.php',
                        'route_label'   => 'Manage Class Timetable',
                        'purpose'       => 'Schedule the Monday–Saturday period timetable for a class section, assigning subjects, buildings, and lecture halls/labs to each period.',
                        'prerequisites' => [
                            'Subjects added to the class and campus buildings/halls configured.',
                        ],
                        'steps'         => [
                            'In **Classes**, click the yellow **Timetable** button on the target class row.',
                            'Select the default **Building** and **Class Hall / Room** for the section if applicable, or configure per-slot rooms.',
                            'In the Monday–Saturday period grid, assign the **Subject(s)** conducted in each period (supports multiple parallel batches/electives in the same period slot).',
                            'Save the timetable and verify that assigned instructors immediately see their slots in **Faculty Weekly Timetable**.',
                        ],
                        'example'       => 'Assigning `DBMS` to Monday Period 1 in `CS-301` and parallel lab batches `DBMS Lab (Group - A)` / `OS Lab (Group - B)` to Monday Periods 5–7.',
                        'validations'   => [
                            'Changes saved here immediately update the Student Portal weekly timetable, the Faculty Weekly Timetable, and the Campus Room Utilization view.',
                        ],
                    ],
                ],
            ],
            [
                'id'          => 'hod-faculty-management',
                'title'       => 'Department Faculty Roster, Profiles & Password Resets',
                'icon'        => 'bi-person-badge',
                'color'       => 'info',
                'description' => 'Manage faculty profile information, seniority/display sort order, active status, and one-click password resets.',
                'stories'     => [
                    [
                        'id'            => 'hod-manage-faculty-roster',
                        'title'         => 'Edit Faculty Profiles, Display Order & Reset Passwords',
                        'icon'          => 'bi-person-gear',
                        'module_key'    => null,
                        'nav_path'      => 'Top Menu → Faculty',
                        'route_url'     => 'hodviewfaculties.php',
                        'route_label'   => 'Manage Faculty Roster',
                        'purpose'       => 'Maintain accurate faculty profile details, customize departmental display order, toggle Active/Inactive status, and reset forgotten faculty passwords.',
                        'prerequisites' => [
                            'Faculty accounts registered under your department.',
                        ],
                        'steps'         => [
                            'Click **Faculty** in the top menu to view all faculty members in your department ordered by seniority/sort order.',
                            '**To View or Edit a Faculty Profile**: Click **Profile** on the faculty member’s row, then click **Edit Profile** to update their **Name**, **Designation**, **Email**, **Mobile Number**, **Display Sort Order**, or **Status** (`Active` / `Inactive`), and save.',
                            '**To Reset a Forgotten Password**: Click the yellow **Reset Pwd** button on the faculty member’s row and confirm the prompt. The faculty member’s password is reset to their registered **Mobile Number**.',
                        ],
                        'example'       => 'When a faculty member is locked out or forgets their password, the HOD clicks **Reset Pwd** beside their name under **Faculty** so they can sign in using their mobile number and then change their password.',
                        'validations'   => [
                            'Ensure the faculty member has a valid mobile number saved in their profile before using **Reset Pwd**.',
                        ],
                    ],
                ],
            ],
            [
                'id'          => 'hod-attendance-and-permissions',
                'title'       => 'Academic Records, Attendance Oversight & Student Permissions',
                'icon'        => 'bi-journal-check',
                'color'       => 'warning',
                'description' => 'Departmental attendance registers, Condonation Gazettes, marking attendance on behalf of faculty, approving deletion requests, and granting NCC/NSS/Sports/OD permissions.',
                'stories'     => [
                    [
                        'id'            => 'hod-subject-and-overall-attendance',
                        'title'         => 'Inspect Subject-Wise Academic Record Books & Overall Class Attendance',
                        'icon'          => 'bi-bar-chart-line',
                        'module_key'    => 'MOD_ATTENDANCE',
                        'nav_path'      => 'Top Menu → Academic Records → Subject-Wise / Overall Class Attendance',
                        'route_url'     => 'hodshowattendance.php',
                        'route_label'   => 'Open Academic Records',
                        'purpose'       => 'Audit attendance, diaries, CIA registers, and e-Bluebooks by individual faculty or class, and generate multi-subject class attendance summaries and Condonation Gazettes.',
                        'prerequisites' => [
                            'Attendance sessions recorded by department faculty.',
                        ],
                        'steps'         => array_values(array_filter([
                            'Click **Academic Records** in the top menu.',
                            '**1. Subject-Wise Inspection**: Click **Attendance by Faculty** or **Attendance by Class**, select the faculty/class and subject, and inspect **Overall Attendance**, **Detailed Attendance & e-Bluebook** (with **Download e-Bluebook PDF**), **Show Diary**, or **Show Continuous Internal Assessment**.',
                            "**2. Overall Class Attendance Summary**: Click **Overall Class Attendance**, select the Specialization and Class, and choose the report mode (**Combined Attendance Summary**, **Enhanced Attendance with Permissions**, or **Subject-wise Breakdown**) to view student percentages and shortage lists (**{$condone}%–{$minAgg}%**, **< {$condone}%**, **< {$minSub}%**).",
                            'Use **Download Excel** to export the class attendance broadsheet.',
                            $showGazette ? 'Click **Download Condonation & Detention Gazette (PDF)** to generate the official regulatory gazette for the class.' : null,
                        ])),
                        'example'       => 'Before mid-semester exams, the HOD opens **Overall Class Attendance** for `III B.Tech I Sem CSE`, reviews the combined attendance percentages across all subjects, and downloads the Excel summary and Condonation Gazette PDF.',
                        'validations'   => [
                            'The Enhanced Attendance view automatically incorporates approved student co-curricular/OD permissions when displaying adjusted attendance statistics.',
                        ],
                    ],
                    [
                        'id'            => 'hod-add-faculty-attendance',
                        'title'         => 'Mark Attendance on Behalf of a Faculty Member',
                        'icon'          => 'bi-plus-square',
                        'module_key'    => 'MOD_ATTENDANCE',
                        'nav_path'      => 'Academic Records → Add New Attendance Record → Add Faculty Attendance',
                        'route_url'     => 'hodfacaddattendance.php',
                        'route_label'   => 'Add Faculty Attendance',
                        'purpose'       => 'Record class attendance and teaching diary on behalf of a department faculty member under exceptional administrative circumstances.',
                        'prerequisites' => [
                            'Subject and students mapped in the department.',
                        ],
                        'steps'         => [
                            'Open **Academic Records** → click **Add Faculty Attendance**.',
                            'Select the **Faculty Member**, **Subject**, and **Date**, then load the student roster and available unmarked hours.',
                            ['text' => 'Enter the **Teaching Diary**, select the conducted **Hour(s)**, verify present/absent students, and submit.', 'requires_write' => true],
                        ],
                        'example'       => 'When a substitute lecture is conducted and needs to be recorded by the department office, the HOD records the session via **Add Faculty Attendance**.',
                        'validations'   => [
                            'Enforces the same classwork date boundaries and unmarked-hour checks as faculty attendance marking.',
                        ],
                    ],
                    [
                        'id'            => 'hod-approve-deletion-requests',
                        'title'         => 'Review & Approve / Reject Attendance Deletion Requests',
                        'icon'          => 'bi-trash3',
                        'module_key'    => 'MOD_ATT_REQUESTS',
                        'nav_path'      => 'Academic Records → Delete Attendance → Show Submitted Requests',
                        'route_url'     => 'hodviewdelrequests.php',
                        'route_label'   => 'Review Deletion Requests',
                        'purpose'       => 'Review formal requests submitted by faculty members to delete erroneously marked attendance sessions and either approve (deleting the session) or reject them.',
                        'prerequisites' => [
                            'Pending deletion requests submitted by faculty.',
                        ],
                        'steps'         => [
                            'Open **Academic Records** → click **Show Submitted Requests to Delete Attendance Records**.',
                            'Inspect the **Pending Requests** table showing the Faculty Name, Subject, Class Date, Hour, Request Timestamp, and Reason.',
                            ['text' => 'Click **Approve** to permanently delete that attendance session and its diary entry (allowing the faculty member to re-mark it accurately), or click **Reject** to keep the existing record intact.', 'requires_write' => true],
                            'Review past decisions in the historical requests table on the same page.',
                        ],
                        'example'       => 'Approving a faculty request to delete a duplicate Period 2 entry so the instructor can re-enter the correct section’s attendance.',
                        'validations'   => [
                            'Approving a request removes both the attendance marks and diary note for that hour and records the approval timestamp.',
                        ],
                    ],
                    [
                        'id'            => 'hod-special-permissions',
                        'title'         => 'Grant & Manage Student Special Attendance Permissions (NCC / NSS / Hackathon / OD)',
                        'icon'          => 'bi-patch-check',
                        'module_key'    => 'MOD_ATTENDANCE',
                        'nav_path'      => 'Academic Records → Special Permission → Manage Special Permissions',
                        'route_url'     => 'hodmanagepermissions.php',
                        'route_label'   => 'Manage Special Permissions',
                        'purpose'       => 'Grant official co-curricular/extra-curricular attendance permissions (NCC, NSS, Hackathon, Workshop, Other) to students for specific dates and hours, with optional supporting document upload.',
                        'prerequisites' => [
                            'Students must belong to the selected class and permission dates must fall within the class start and end dates.',
                        ],
                        'steps'         => [
                            'Open **Academic Records** → click **Manage Special Permissions**.',
                            ['text' => '**Step 1 — Input Permission Details (`Grant Permission` tab)**: Select **Specialization**, **Class**, and **Permission Type** (`NCC`, `NSS`, `Hackathon`, `Workshop`, `Other`). Enter comma- or line-separated **Roll Numbers** and the **Reason / Event Name**.', 'requires_write' => true],
                            ['text' => 'Click **Add Date** to add one or more dates, choosing either **All Day** or **Selected Hours** for each date, then click **Preview**.', 'requires_write' => true],
                            ['text' => '**Step 2 — Preview & Confirm**: Review the grouped student-and-hour preview table, optionally attach a **Supporting Document** (`.pdf`, `.jpg`, `.png`), and click **Confirm & Save**.', 'requires_write' => true],
                            'Switch to the **View & Manage** tab and filter by Specialization, Class, and Month to inspect granted permissions, view uploaded documents, or delete individual hour-slots / complete days.',
                        ],
                        'example'       => 'Granting `All Day` permission under `NCC` for 5 students attending the NCC Annual Training Camp across 3 dates, attaching the sanction letter PDF, and confirming.',
                        'validations'   => [
                            'The system automatically validates that all roll numbers belong to the selected class, dates are within the class period, and no duplicate permission exists for the same student, date, and hour.',
                        ],
                    ],
                ],
            ],
            [
                'id'          => 'hod-analytics-feedback-cqi-results',
                'title'       => 'Departmental CIA, OBE Analytics, Feedback, Results & CQI Approvals',
                'icon'        => 'bi-graph-up',
                'color'       => 'success',
                'description' => 'Department-wide internal marks ledgers, OBE attainment analytics, student feedback reports, published exam results, and Course Completion / CQI approval queues.',
                'stories'     => array_values(array_filter([
                    [
                        'id'            => 'hod-class-cia-and-obe-analysis',
                        'title'         => 'Inspect Class CIA Ledgers & Course-Wise OBE Attainment Analytics',
                        'icon'          => 'bi-file-earmark-spreadsheet',
                        'module_key'    => 'MOD_OBE_ANALYSIS',
                        'nav_path'      => 'Academic Records → Analysis & Reports → Class-wise CIA Marks Ledger / Course-wise CIA Analysis',
                        'route_url'     => 'hodciaanalysis2.php',
                        'route_label'   => 'Open HOD CIA Analysis',
                        'purpose'       => 'Review consolidated CIA marks across all subjects in a class and inspect detailed course-wise CO/PO attainment, Bloom’s cognitive charts, and accreditation dossiers.',
                        'prerequisites' => [
                            'CIA marks submitted by course instructors.',
                        ],
                        'steps'         => array_values(array_filter([
                            '**Class-Wise CIA Marks Ledger**: From **Academic Records**, click **Class-wise CIA Marks Ledger**, select a class, and view or download the multi-subject internal marks summary.',
                            '**Course-Wise CIA & OBE Attainment**: Click **Course-wise CIA Analysis**, select the **Class**, **Subject**, and **Assessment** (`CIA 1`, `CIA 2`, `Overall CIA`, `Lab CIA`' . ($showSeeMarks ? ', or `SEE`' : '') . '), and click **Load Analysis**.',
                            'Review the **Overview**, **Outcome Analysis (CO/PO)**, **Cognitive Level (Bloom’s)**, **CO-PO Matrix**, and **Other Insights** tabs.',
                            'Download the **OBE Analytics Dossier**' . ($showSarFormC3 ? ', **NBA SAR Form C-3**,' : '') . ' or **e-Bluebook Course File** from the report footer.',
                        ])),
                        'example'       => 'Reviewing `III B.Tech I Sem CSE` course attainment under **Course-wise CIA Analysis** and exporting the class CIA ledger.',
                        'validations'   => [
                            'HOD analysis views are scoped to courses and classes belonging to your department.',
                        ],
                    ],
                    [
                        'id'            => 'hod-feedback-reports',
                        'title'         => 'Review Departmental Student Feedback Reports',
                        'icon'          => 'bi-chat-dots',
                        'module_key'    => 'MOD_FEEDBACK',
                        'nav_path'      => 'Academic Records → Analysis & Reports → Course-wise Feedback',
                        'route_url'     => 'hodviewfeedback.php',
                        'route_label'   => 'Open Feedback Reports',
                        'purpose'       => 'Evaluate student feedback across courses, faculty members, and classes in your department and export PDF, Excel, or CSV reports.',
                        'prerequisites' => [
                            'Student feedback module enabled and student responses submitted.',
                        ],
                        'steps'         => [
                            'Open **Academic Records** → click **Course-wise Feedback**.',
                            'Choose the report level (Subject-wise, Faculty-wise, or Class-wise), select the **Academic Year** and target class/subject/faculty, and load the report.',
                            'Inspect response rates, CO indirect survey scores, Course End Survey (CES) metrics, teaching effectiveness ratings, and student remarks.',
                            'Export the report using **Export PDF**, **Export Excel**, or **Export CSV**.',
                        ],
                        'example'       => 'Generating the department-wide faculty feedback summary for the academic year under **Course-wise Feedback**.',
                        'validations'   => [
                            'Individual student identities remain strictly anonymous in all departmental feedback views and exports.',
                        ],
                    ],
                    ($showCqi || $showLessonPlan) ? [
                        'id'            => 'hod-review-queue-cqi',
                        'title'         => 'Approve Course Completion Audits & CQI Remedial Actions',
                        'icon'          => 'bi-clipboard-check',
                        'module_key'    => $showCqi ? 'MOD_CQI_ACTION_TRACKING' : 'MOD_LESSON_PLAN',
                        'nav_path'      => 'HOD Home → Course review and action tracking',
                        'route_url'     => 'reviewqueue.php',
                        'route_label'   => 'Open Review Queue',
                        'purpose'       => 'Review and approve/return end-of-semester Course Completion Audits (NBA Criterion 2.2) and Continuous Quality Improvement (CQI) remedial action plans submitted by department faculty.',
                        'prerequisites' => [
                            'Submissions made by faculty under **Course Delivery Audit & Reconciliation**.',
                        ],
                        'steps'         => [
                            'On **HOD Home**, click **Course review and action tracking**.',
                            '**For Course Delivery Audits (`COURSE` items)**: Review the planned vs. conducted lecture counts, syllabus coverage %, unit completion dates, and deviation disclosures. Enter **Observations / execution evidence**, select **APPROVED** (or **REJECTED** if corrections are required), and click **Save decision**.',
                            '**For CQI Action Items (`CQI` items)**: Review the proposed remedial action for an unattained CO. Select **APPROVED** (with an optional **Due date**) or **REVISION_REQUESTED**. Once the faculty member completes the intervention (`COMPLETED`), review their execution evidence, enter your follow-up verification remarks, and select **CLOSED**.',
                            'Click **Download Action Taken Report** on any CQI card to export the signed ATR PDF.',
                        ],
                        'example'       => 'Verifying a faculty member’s completed remedial tutorial evidence in **Course review and action tracking** and transitioning the CQI item to `CLOSED`.',
                        'validations'   => [
                            'Every review decision is permanently recorded in the workflow history with reviewer identity, timestamp, and remarks.',
                        ],
                    ] : null,
                    [
                        'id'            => 'hod-department-results',
                        'title'         => 'Inspect Department Examination Results, Pass % & Backlogs',
                        'icon'          => 'bi-award',
                        'module_key'    => 'MOD_RESULTS_PUBLISH',
                        'nav_path'      => 'Academic Records → Analysis & Reports → Department Examination Results',
                        'route_url'     => 'hodresults.php',
                        'route_label'   => 'Open Dept Results',
                        'purpose'       => 'Analyze published semester examination results for your department, including subject-wise pass percentages, student SGPA/CGPA grade cards, and active backlog rosters.',
                        'prerequisites' => [
                            'Examination results published by the Academic Section.',
                        ],
                        'steps'         => [
                            'Open **Academic Records** → click **Department Examination Results**.',
                            'Filter by **Examination Notification**, **Class**, or **Subject** to inspect cohort pass percentages, grade distributions, and top performers.',
                            'Switch views to inspect individual student grade sheets or identify students with subject backlogs for remedial mentoring.',
                        ],
                        'example'       => 'Reviewing subject-wise pass percentages for `III B.Tech I Sem CSE` immediately after regular exam results are published.',
                        'validations'   => [
                            'Read-only departmental analytics view scoped to your department’s specializations.',
                        ],
                    ],
                    ($showSeeMarks || $showAiAssist) ? [
                        'id'            => 'hod-attainment-import-ai',
                        'title'         => 'Import Attainment Evidence & Review AI Drafts',
                        'icon'          => 'bi-cloud-arrow-up',
                        'module_key'    => $showSeeMarks ? 'MOD_SEE_MARKS' : 'MOD_AI_ASSISTANCE',
                        'nav_path'      => 'HOD Home → Import attainment evidence / Review AI drafts',
                        'route_url'     => $showSeeMarks ? 'attainmentimport.php' : 'aiassistance.php',
                        'route_label'   => 'Open Tool',
                        'purpose'       => 'Upload verified external attainment CSVs or review opt-in AI attainment/ATR drafts for department courses.',
                        'prerequisites' => [
                            'Respective external attainment or AI assistance feature enabled for HOD.',
                        ],
                        'steps'         => array_values(array_filter([
                            $showSeeMarks ? 'Click **Import attainment evidence** on **HOD Home** to preview and commit external marks/grades CSVs for departmental courses.' : null,
                            $showAiAssist ? 'Click **Review AI drafts** on **HOD Home** to inspect or approve queued AI draft narratives.' : null,
                        ])),
                        'example'       => 'Reviewing a course attainment import preview before finalizing department NBA Criterion 3 tables.',
                        'validations'   => [
                            'Scoped strictly to courses within your department.',
                        ],
                    ] : null,
                ])),
            ],
        ];
    }

    // =========================================================================
    // 3. ADMIN (PRINCIPAL) CATALOG
    // =========================================================================
    private function buildAdminCatalog(array $reg): array
    {
        $showGazette     = $this->isModuleVisibleForRole('MOD_ATTENDANCE_CONDONATION_GAZETTE', 'admin');
        $showCreditPromo = FeatureManager::isModuleEnabled('MOD_CREDIT_PROMOTION');

        return [
            [
                'id'          => 'admin-executive-dashboard-and-departments',
                'title'       => 'Principal’s Executive Hub, Departments, Faculty & Student Enrollment',
                'icon'        => 'bi-building',
                'color'       => 'primary',
                'description' => 'Institution-wide oversight of academic departments, HOD profiles, teaching faculty onboarding, and class student enrollment rosters.',
                'stories'     => [
                    [
                        'id'            => 'admin-dashboard-overview',
                        'title'         => 'Navigate the Principal’s Executive Dashboard',
                        'icon'          => 'bi-briefcase',
                        'module_key'    => null,
                        'nav_path'      => 'Top Menu → Home',
                        'route_url'     => 'adminhome.php',
                        'route_label'   => 'Open Executive Dashboard',
                        'purpose'       => 'Access institution-wide KPIs and quick links across Departments, Attendance Monitoring, Student Feedback Surveys, Password Support, Exam Results, Student Profiles, and Certificates.',
                        'prerequisites' => [
                            'Authenticated Principal / Admin account.',
                        ],
                        'steps'         => [
                            'Sign in at the Staff Portal login page to open the **Principal’s Executive Dashboard**.',
                            'Use the top navigation dropdowns (**Home**, **Departments**, **Attendance**, **Quality & Ops**, **Accounts**, **Logout**) or the 4 Executive Hub cards to jump directly to any institutional workflow.',
                        ],
                        'example'       => 'Opening the Executive Dashboard to check total active departments and jump to Live Survey Submission Status.',
                        'validations'   => [
                            'Principal / Admin role has institution-wide visibility across all academic departments.',
                        ],
                    ],
                    [
                        'id'            => 'admin-manage-hod-and-faculty',
                        'title'         => 'Manage Department HOD Profiles & Teaching Faculty Rosters',
                        'icon'          => 'bi-person-check',
                        'module_key'    => null,
                        'nav_path'      => 'Top Menu → Departments → HOD Details / Faculty',
                        'route_url'     => 'adminfacst.php',
                        'route_label'   => 'Open Departments Hub',
                        'purpose'       => 'Update Head of Department (HOD) leadership details and onboard, edit, reorder, or activate/deactivate teaching faculty across any academic department.',
                        'prerequisites' => [
                            'Departments configured in the system.',
                        ],
                        'steps'         => [
                            'Click **Departments** in the top navigation bar.',
                            '**To View/Update HOD Details**: Click **HOD Details** on the department row, then click **Edit** to update the HOD’s name, email, or mobile number.',
                            '**To Manage Department Faculty**: Click **Faculty** on the department row.',
                            'Click **Add New Faculty** to register a new instructor (entering Username, Full Name, Designation, Email, Mobile Number, and Initial Password).',
                            'Click **Profile** (and **Edit**) beside any existing faculty member to update their designation, contact details, custom display sort order, or `Active`/`Inactive` status.',
                        ],
                        'example'       => 'Onboarding a newly recruited Assistant Professor in the ECE department via **Departments → Faculty → Add New Faculty**.',
                        'validations'   => [
                            'Faculty display sort order controls the sequence in which faculty appear across departmental rosters and reports.',
                        ],
                    ],
                    [
                        'id'            => 'admin-manage-classes-and-students',
                        'title'         => 'Enroll, Bulk Upload, Edit, or Unenroll Class Students',
                        'icon'          => 'bi-people',
                        'module_key'    => null,
                        'nav_path'      => 'Top Menu → Departments → Classes',
                        'route_url'     => 'adminfacst.php',
                        'route_label'   => 'Manage Class Students',
                        'purpose'       => 'Enroll student cohorts into semester classes via bulk Excel/CSV upload or individual roll entry, inspect class rosters, edit student particulars, or unenroll erroneously added students.',
                        'prerequisites' => [
                            'Class sections created for the specialization and academic year.',
                        ],
                        'steps'         => [
                            'Open **Departments** and click **Classes** on the target department row.',
                            'Expand the **Specialization** and **Academic Year** to locate the target class section:',
                            '• **Enroll Students**: Click **Enroll Students** to upload an Excel/CSV student list (Roll Number, Student Name, Email, Mobile, Date of Joining) or enroll students into the class.',
                            '• **View & Edit Students**: Click **View Students** to inspect the enrolled roster and click **Edit** beside any student to update their name, contact info, or active status.',
                            '• **Unenroll Students**: Click **Unenroll Students** to remove students who were enrolled in the wrong class section (allowed before attendance/marks are recorded).',
                        ],
                        'example'       => 'Uploading the 66-student admission roster for `I B.Tech I Sem CSE - Sec A` via **Departments → Classes → Enroll Students**.',
                        'validations'   => [
                            'Once students are enrolled in a class, the department HOD can map them to individual subjects and lab batches.',
                        ],
                    ],
                ],
            ],
            [
                'id'          => 'admin-attendance-and-timetable',
                'title'       => 'Institutional Attendance Monitoring & Campus Timetable',
                'icon'        => 'bi-calendar-check',
                'color'       => 'success',
                'description' => 'College-wide attendance auditing by faculty, by class, and multi-subject aggregates, Condonation Gazettes, and live classroom occupancy.',
                'stories'     => [
                    [
                        'id'            => 'admin-attendance-monitoring',
                        'title'         => 'Monitor Attendance by Faculty, by Class & Overall Class Summary',
                        'icon'          => 'bi-bar-chart-line',
                        'module_key'    => 'MOD_ATTENDANCE',
                        'nav_path'      => 'Top Menu → Attendance Dropdown → Attendance by Faculty / Attendance by Class / Overall Class Attendance Summary',
                        'route_url'     => 'adminshowallclsattendance.php',
                        'route_label'   => 'Open Attendance Summary',
                        'purpose'       => 'Audit daily attendance delivery, teaching diaries, e-Bluebooks, and cumulative student attendance percentages across all departments and classes.',
                        'prerequisites' => [
                            'Attendance sessions recorded by faculty.',
                        ],
                        'steps'         => array_values(array_filter([
                            '**1. Attendance by Faculty**: Select any **Department**, **Faculty Member**, and **Subject** to view overall attendance, detailed period grids, teaching diary logs, CIA marks, and download the official **e-Bluebook PDF**.',
                            '**2. Attendance by Class**: Select a **Department**, **Specialization**, **Class**, and **Subject** to inspect subject-level attendance registers.',
                            '**3. Overall Class Attendance Summary**: Select a **Department**, **Specialization**, and **Class** to view the combined multi-subject attendance table (Standard, Enhanced with Permissions, or Subject-wise), export to Excel,' . ($showGazette ? ' or click **Download Condonation & Detention Gazette (PDF)**.' : ' and review shortage lists.'),
                            '**4. Campus Timetable & Room Usage**: Open **Attendance → Campus Timetable & Room Usage** to inspect live room occupancy and free halls across campus buildings.',
                        ])),
                        'example'       => 'The Principal opens **Overall Class Attendance Summary** at semester end to review condonation (`65%–75%`) and detention (`<65%`) lists across departments.',
                        'validations'   => [
                            'Provides full cross-departmental visibility and official PDF/Excel exports.',
                        ],
                    ],
                ],
            ],
            [
                'id'          => 'admin-quality-feedback-and-cia',
                'title'       => 'Quality Assurance: Live Feedback Monitor, Survey Reports & CIA Analytics',
                'icon'        => 'bi-graph-up-arrow',
                'color'       => 'info',
                'description' => 'Track real-time student survey completion, export institutional feedback dossiers, and inspect course-level CIA/OBE attainment.',
                'stories'     => [
                    [
                        'id'            => 'admin-feedback-monitoring-and-reports',
                        'title'         => 'Track Live Feedback Submission Status & Export Feedback Reports',
                        'icon'          => 'bi-check2-circle',
                        'module_key'    => 'MOD_FEEDBACK',
                        'nav_path'      => 'Top Menu → Quality & Ops → Survey Submission Status / Course Feedback Survey Reports',
                        'route_url'     => 'adminshowfeedbackstatus.php',
                        'route_label'   => 'Open Live Feedback Status',
                        'purpose'       => 'Monitor real-time student feedback completion rates by department/class and generate comprehensive subject, faculty, and department feedback reports.',
                        'prerequisites' => [
                            'Student Feedback module enabled.',
                        ],
                        'steps'         => [
                            '**1. Live Submission Monitor (`Survey Submission Status`)**: Select **Department**, **Specialization**, and **Class** to view exact submission counts, completion percentages, and pending student roll numbers for each subject.',
                            '**2. Comprehensive Feedback Reports (`Course Feedback Survey Reports`)**: Select the report type (Subject-wise, Faculty-wise, Class-wise, or Department Summary), filter by Academic Year and Department, and inspect ratings across Teaching Appraisal, Course Outcomes (CO), and Course End Survey (CES).',
                            'Click **Export PDF**, **Export Excel**, or **Export CSV** to download official quality assurance reports.',
                        ],
                        'example'       => 'Checking **Survey Submission Status** during the feedback week to identify classes with <80% turnout and downloading the college-wide Excel feedback summary.',
                        'validations'   => [
                            'While **Survey Submission Status** tracks whether a student has completed their submission, the actual ratings and comments in **Course Feedback Survey Reports** are completely anonymized.',
                        ],
                    ],
                    [
                        'id'            => 'admin-cia-obe-analysis',
                        'title'         => 'Inspect Institutional CIA Performance & OBE Attainment',
                        'icon'          => 'bi-pie-chart',
                        'module_key'    => 'MOD_OBE_ANALYSIS',
                        'nav_path'      => 'Top Menu → Quality & Ops → CIA Performance & Attainment',
                        'route_url'     => 'adminciaanalysis2.php',
                        'route_label'   => 'Open CIA & OBE Analysis',
                        'purpose'       => 'Review course-level Continuous Internal Assessment performance, CO/PO attainment levels, and Bloom’s taxonomy distributions across any department.',
                        'prerequisites' => [
                            'OBE Analysis module enabled and detailed CIA/SEE marks recorded.',
                        ],
                        'steps'         => [
                            'Open **Quality & Ops → CIA Performance & Attainment**.',
                            'Select the **Department**, **Specialization**, **Class**, **Subject**, and **Assessment Scope**, then click **Load Analysis**.',
                            'Review the attainment charts and matrices, and download the **OBE Analytics Dossier** or **e-Bluebook** from the footer.',
                        ],
                        'example'       => 'Auditing CO attainment and Bloom’s cognitive balance for a core course during an internal quality audit.',
                        'validations'   => [
                            'Uses the same standardized attainment formulas across Faculty, HOD, and Principal views.',
                        ],
                    ],
                ],
            ],
            [
                'id'          => 'admin-exams-dossiers-certificates-accounts',
                'title'       => 'Exam Results, Student Dossiers, Custody Ledger & Account Support',
                'icon'        => 'bi-mortarboard',
                'color'       => 'warning',
                'description' => 'Manage semester examination publications, verify student digital vaults, oversee original certificate custody and statutory certificates, and reset passwords.',
                'stories'     => [
                    [
                        'id'            => 'admin-exam-results-management',
                        'title'         => 'Oversee Semester Exam Results, CIA Reconciliation & Promotion Simulations',
                        'icon'          => 'bi-file-earmark-spreadsheet',
                        'module_key'    => 'MOD_RESULTS_PUBLISH',
                        'nav_path'      => 'Top Menu → Quality & Ops → Exam Results Overview',
                        'route_url'     => 'academicsectionresults.php',
                        'route_label'   => 'Open Exam Results',
                        'purpose'       => 'Create examination notifications, upload/preview result CSVs, reconcile Portal CIA vs. Exam Internal marks, run credit promotion simulations, and publish results.',
                        'prerequisites' => [
                            'Examination Results module enabled.',
                        ],
                        'steps'         => array_values(array_filter([
                            'Open **Quality & Ops → Exam Results Overview**.',
                            'In **Tab 1 (Examination Results & Ingestion)**: Click **New Exam Notification** to register an exam session (`REGULAR`, `SUPPLEMENTARY`, `REVALUATION`), click **Upload CSV** to validate and preview results, click **Mark reviewed** after verification, and toggle the eye icon to **Publish** results to students (which also auto-syncs regular SEE marks).',
                            $showCreditPromo ? 'In **Tab 2 (Credit Promotion Simulation)**: Select a class and click **Run Advisory Simulation** to evaluate R23 Section 18 credit thresholds and record **Clear by Committee** resolutions where authorized.' : null,
                            'In **CIA vs Exam Internal Reconciliation**: Select an exam notification to compare portal-calculated CIA marks against uploaded CSV internal marks and export the discrepancy audit CSV.',
                        ])),
                        'example'       => 'Reviewing the CIA vs. Uploaded Internal reconciliation table before publishing regular semester results.',
                        'validations'   => [
                            'Results follow a strict `DRAFT` → `REVIEWED` → `PUBLISHED` governance lifecycle.',
                        ],
                    ],
                    [
                        'id'            => 'admin-student-profiles-and-vault',
                        'title'         => 'Inspect & Verify Student Profiles and Digital Document Vault',
                        'icon'          => 'bi-person-badge',
                        'module_key'    => 'MOD_STUDENT_PROFILE',
                        'nav_path'      => 'Top Menu → Quality & Ops → Student Dossier & Profiles',
                        'route_url'     => 'adminstudentprofiles.php',
                        'route_label'   => 'Open Student Profiles',
                        'purpose'       => 'Search any student across the college, inspect their personal/parental/admission dossier and uploaded documents, and mark profiles and credentials as Verified.',
                        'prerequisites' => [
                            'Student Profile module enabled.',
                        ],
                        'steps'         => [
                            'Open **Quality & Ops → Student Dossier & Profiles**.',
                            'Search by **Roll No / Username** or filter by **Department** and **Class**, then click **View Dossier**.',
                            'Review the student’s Personal Particulars, Parental Details, Admission & Quota metrics, and Uploaded Documents.',
                            ['text' => 'Click **View** to inspect each uploaded document, click **Verify** beside valid documents, and click **Mark Profile Verified** in the dossier header.', 'requires_write' => true],
                        ],
                        'example'       => 'Verifying a student’s SSC, Intermediate, and EAPCET allotment documents under **Student Dossier & Profiles**.',
                        'validations'   => [
                            'All verification actions record the verifier’s user identity and timestamp.',
                        ],
                    ],
                    [
                        'id'            => 'admin-custody-and-certificates',
                        'title'         => 'Manage Original Certificate Custody & Issue Statutory Certificates',
                        'icon'          => 'bi-journal-bookmark',
                        'module_key'    => 'MOD_CERTIFICATES',
                        'nav_path'      => 'Top Menu → Quality & Ops → Custodial & Certificates Ledger',
                        'route_url'     => 'admincustodyledger.php',
                        'route_label'   => 'Open Custody & Certificates',
                        'purpose'       => 'Track physical original certificates deposited by students (`IN_CUSTODY`, `TEMPORARILY_RETURNED`, `PERMANENTLY_RETURNED`) and process incoming student certificate applications.',
                        'prerequisites' => [
                            'Certificates & Custody module enabled.',
                        ],
                        'steps'         => [
                            'Open **Quality & Ops → Custodial & Certificates Ledger**.',
                            ['text' => '**Tab 1 — Physical Custody Ledger**: Use the left form (**Receive Original Document**) to register an original certificate (Roll No, Certificate Name, Serial No, Received Date, Rack Reference). Use **Checkout** for temporary student withdrawals, **Check In** when returned, and **Exit Return** for permanent handover at graduation.', 'requires_write' => true],
                            ['text' => '**Tab 2 — Statutory Certificate Requests**: Filter incoming student applications (`CUSTODIAL`, `BONAFIDE`, `STUDY_CONDUCT`, `TRANSFER_CERTIFICATE`, `NO_DUES`), click the green checkmark to **Approve**, click **Print** to generate the formatted certificate (with QR verification when enabled), and click **Issue**.', 'requires_write' => true],
                        ],
                        'example'       => 'Approving a student’s Bonafide Certificate request in Tab 2 and printing the official certificate.',
                        'validations'   => [
                            'Issued certificates automatically receive a sequential serial number and verification token.',
                        ],
                    ],
                    [
                        'id'            => 'admin-password-resets',
                        'title'         => 'Reset Student & Faculty Passwords (Audited)',
                        'icon'          => 'bi-key',
                        'module_key'    => null,
                        'nav_path'      => 'Top Menu → Accounts Dropdown → Reset Student Password / Reset Faculty Password / Change My Password',
                        'route_url'     => 'adminresetstudentpwd.php',
                        'route_label'   => 'Open Password Support',
                        'purpose'       => 'Reset forgotten login passwords for students or faculty with mandatory audit logging.',
                        'prerequisites' => [
                            'Valid student Roll Number or faculty Username.',
                        ],
                        'steps'         => [
                            'Click **Accounts** in the top menu and choose **Reset Student Password** or **Reset Faculty Password**.',
                            'Enter the Username / Roll Number to look up and confirm the user’s account details.',
                            'Enter the new password and mandatory administrative reason/remarks, then submit the reset.',
                            'To update your own Principal/Admin password, open **Accounts → Change My Password**.',
                        ],
                        'example'       => 'Resetting a student’s portal password under **Accounts → Reset Student Password** after verifying their college ID card.',
                        'validations'   => [
                            'Every administrative password reset is permanently logged with the administrator’s identity, target user, and justification.',
                        ],
                    ],
                ],
            ],
        ];
    }

    // =========================================================================
    // 4. ACADEMIC SECTION CATALOG
    // =========================================================================
    private function buildAcademicSectionCatalog(array $reg): array
    {
        $showGazette     = $this->isModuleVisibleForRole('MOD_ATTENDANCE_CONDONATION_GAZETTE', 'academic_section');
        $showCreditPromo = FeatureManager::isModuleEnabled('MOD_CREDIT_PROMOTION');
        $showSeeMarks    = $this->isModuleVisibleForRole('MOD_SEE_MARKS', 'academic_section');
        $showAiAssist    = $this->isModuleVisibleForRole('MOD_AI_ASSISTANCE', 'academic_section');

        return [
            [
                'id'          => 'acad-curriculum-and-bos-setup',
                'title'       => 'Academic Setup: Regulations, Course Structure, Master Curriculum & Syllabus',
                'icon'        => 'bi-diagram-2',
                'color'       => 'primary',
                'description' => 'Maintain institutional regulations, multi-semester Course Structure roadmaps, statutory credit compliance, Master Curriculum Subjects, Master COs/Matrices, and official syllabi.',
                'stories'     => [
                    [
                        'id'            => 'acad-course-structure-engine',
                        'title'         => 'Manage Multi-Semester Course Structure & BoS Dossier',
                        'icon'          => 'bi-diagram-2-fill',
                        'module_key'    => null,
                        'nav_path'      => 'Top Menu → Academic Setup → Course Structure',
                        'route_url'     => 'academicsectioncoursestructure.php',
                        'route_label'   => 'Open Course Structure',
                        'purpose'       => 'Configure and audit the complete semester-by-semester Course Structure for each Program, Regulation, and Branch, verify statutory category credit limits, and print/export the Board of Studies (BoS) Dossier.',
                        'prerequisites' => [
                            'Programs, Regulations, and Specializations configured in the system.',
                        ],
                        'steps'         => [
                            'Open **Academic Setup → Course Structure**.',
                            'Select the **Program**, **Regulation**, and **Branch / Specialization**, then click **Load Structure**.',
                            '**Tab 1 — Semester-by-Semester Roadmap**: Inspect all semesters (`I Yr - I Sem` through `IV Yr - II Sem`), click **Add Course to Structure** (or **Add to <Sem>**) to add a course with auto-calculated L-T-P-C credits and CIE/SEE marks, or click the pencil icon to edit an existing course.',
                            '**Tab 2 — Statutory Category Credit Compliance**: Verify that allocated credits across categories (`BS`, `ES`, `HS`, `PC`, `PE`, `OE`, `MC`, `PR`, `SC`) match the target degree credits (`100% Compliant` vs `Deficit` / `Excess`).',
                            '**Tab 3 — Elective Tracks & Verticals**: Review Professional Electives (`PE`), Open Electives (`OE`), and Minor/Honors tracks.',
                            'Use **Export CSV** or **Print BoS Dossier** in the top-right bar to generate official Board of Studies documentation.',
                        ],
                        'example'       => 'Loading `B.Tech - R23 - CSE` under **Course Structure** to verify that total degree credits equal `160.0 / 160.0` (`100% Compliant`) and printing the BoS Dossier.',
                        'validations'   => [
                            'Credits auto-calculate using the standard statutory formula `C = L + T + 0.5 × P` while remaining editable for special courses.',
                        ],
                    ],
                    [
                        'id'            => 'acad-curriculum-subjects-master-cos',
                        'title'         => 'Manage Curriculum Subjects, Master COs & Master Articulation Matrix',
                        'icon'          => 'bi-book',
                        'module_key'    => null,
                        'nav_path'      => 'Top Menu → Academic Setup → Curriculum Subjects',
                        'route_url'     => 'academicsectioncurriculumsubjects.php',
                        'route_label'   => 'Open Curriculum Subjects',
                        'purpose'       => 'Maintain the Master Subject Catalog for each semester and define the central Master Course Outcomes (`Master COs`) and Master CO-PO/PSO Articulation Matrix (`Matrix`) inherited by department class offerings.',
                        'prerequisites' => [
                            'Program, Regulation, and Specialization available.',
                        ],
                        'steps'         => [
                            'Open **Academic Setup → Curriculum Subjects**.',
                            'Select **Program**, **Regulation**, **Specialization**, and **Year - Sem**, then click **Get Subjects**.',
                            'Use the **Add New Curriculum Subject** form to add a subject (typing the **Subject Code** automatically looks up catalog details if the code already exists in another branch) and click **Save Subject**.',
                            'In the **Defined Subjects** table, click **Master COs** on any subject row to open the modal and add/edit approved Course Outcomes (`CO1`..`CO6`), Bloom’s levels, and target thresholds.',
                            'Click **Matrix** on the subject row to open the **Curriculum Master Articulation Matrix** modal, assign CO-to-PO/PSO correlation weights (`1`, `2`, `3`), and click **Save Master Articulation Matrix** (which automatically cascades to active offerings).',
                        ],
                        'example'       => 'Defining 5 Master COs and the Master CO-PO Matrix for `23A05301T (Data Structures)` so HODs and faculty inherit the approved syllabus outcomes automatically.',
                        'validations'   => [
                            'Saving the Master Articulation Matrix updates the curriculum catalog and cascades missing mappings to active class offerings.',
                        ],
                    ],
                    [
                        'id'            => 'acad-syllabus-and-regulations',
                        'title'         => 'Maintain Official Syllabus Records & Review Regulations',
                        'icon'          => 'bi-file-earmark-text',
                        'module_key'    => null,
                        'nav_path'      => 'Top Menu → Academic Setup → Regulations / Syllabus',
                        'route_url'     => 'academicsectionsyllabus.php',
                        'route_label'   => 'Open Syllabus Manager',
                        'purpose'       => 'Upload and organize official syllabus documents and review regulation structures and attendance rules.',
                        'prerequisites' => [
                            'Approved syllabus files.',
                        ],
                        'steps'         => [
                            'Open **Academic Setup → Syllabus** to upload, group, and maintain syllabus records by regulation and specialization.',
                            'Open **Academic Setup → Regulations** to inspect active regulations and attendance rule parameters.',
                        ],
                        'example'       => 'Uploading the R23 B.Tech CSE syllabus document under **Academic Setup → Syllabus**.',
                        'validations'   => [
                            'Provides centralized reference documents across the institution.',
                        ],
                    ],
                ],
            ],
            [
                'id'          => 'acad-enrollment-and-buildings',
                'title'       => 'Student Enrollment, Bulk Uploads & Campus Buildings / Halls',
                'icon'        => 'bi-people',
                'color'       => 'success',
                'description' => 'Manage class student rosters across departments, execute direct bulk student uploads, and maintain campus buildings and lecture halls.',
                'stories'     => [
                    [
                        'id'            => 'acad-student-enrollment-and-bulk-upload',
                        'title'         => 'Enroll Students in Department Classes & Direct Bulk Upload',
                        'icon'          => 'bi-file-earmark-arrow-up',
                        'module_key'    => null,
                        'nav_path'      => 'Top Menu → Academic Setup → Class Allotment & Student Enrollment / Direct Bulk Upload Students',
                        'route_url'     => 'adminfacst.php',
                        'route_label'   => 'Open Enrollment Hub',
                        'purpose'       => 'Populate and maintain student rosters for every class cohort across all departments so HODs can map students to subjects.',
                        'prerequisites' => [
                            'Classes created for the academic year.',
                        ],
                        'steps'         => [
                            '**Path A — Department Class Hub**: Open **Academic Setup → Class Allotment & Student Enrollment**, click **Classes** beside the target department, and use **Enroll Students**, **View Students** (with **Edit**), or **Unenroll Students**.',
                            '**Path B — Direct Bulk Upload**: Open **Academic Setup → Direct Bulk Upload Students**, select the target class, upload the student CSV/Excel file, and verify the enrolled count.',
                        ],
                        'example'       => 'Bulk uploading newly admitted I B.Tech students and Lateral Entry II B.Tech students into their respective class sections.',
                        'validations'   => [
                            'Under **Class Allotment & Student Enrollment**, the Academic Section is scoped specifically to **Classes & Students** (HOD Leadership and Faculty Roster management remain Principal/Admin-only).',
                        ],
                    ],
                    [
                        'id'            => 'acad-manage-buildings-halls',
                        'title'         => 'Manage Campus Buildings & Lecture Halls / Laboratories',
                        'icon'          => 'bi-building',
                        'module_key'    => null,
                        'nav_path'      => 'Top Menu → Academic Setup → Buildings & Halls',
                        'route_url'     => 'academicsectionmanagebuildings.php',
                        'route_label'   => 'Manage Buildings & Halls',
                        'purpose'       => 'Add, rename, or activate/deactivate campus buildings and lecture halls/labs used in class timetable scheduling and room occupancy monitoring.',
                        'prerequisites' => [
                            'Authenticated Academic Section session.',
                        ],
                        'steps'         => [
                            'Open **Academic Setup → Buildings & Halls**.',
                            'Add new **Buildings** (e.g., `Lecture Hall Complex`, `Department of CSE`) or toggle their active status.',
                            'Add **Halls / Rooms** under each building and maintain their status so HODs can select them when scheduling class timetables.',
                        ],
                        'example'       => 'Adding `Room 304` and `AI Lab 2` under `Department of CSE` in **Buildings & Halls**.',
                        'validations'   => [
                            'Active buildings and halls immediately populate the room selectors across class timetables and campus room utilization views.',
                        ],
                    ],
                ],
            ],
            [
                'id'          => 'acad-views-attendance-timetables',
                'title'       => 'Academic Views: Attendance Oversight & Campus / Faculty Timetables',
                'icon'        => 'bi-eye',
                'color'       => 'info',
                'description' => 'Institution-wide class attendance summaries, Condonation & Detention Gazettes, live room utilization, and faculty schedules.',
                'stories'     => [
                    [
                        'id'            => 'acad-attendance-and-timetables',
                        'title'         => 'Monitor Class Attendance, Generate Gazette & Inspect Timetables',
                        'icon'          => 'bi-calendar-check',
                        'module_key'    => 'MOD_ATTENDANCE',
                        'nav_path'      => 'Top Menu → Academic Views → View Attendance / Campus Timetable & Room Usage / Faculty-wise Timetable',
                        'route_url'     => 'academicsectionshowallclsattendance.php',
                        'route_label'   => 'View Class Attendance',
                        'purpose'       => 'Monitor cumulative student attendance across all departments, export Excel broadsheets and Condonation Gazettes, and inspect campus room and faculty schedules.',
                        'prerequisites' => [
                            'Attendance marked by faculty and timetables scheduled by HODs.',
                        ],
                        'steps'         => array_values(array_filter([
                            '**1. View Attendance**: Open **Academic Views → View Attendance**, select **Department**, **Specialization**, and **Class** to inspect combined class attendance percentages and shortage brackets (`75%`, `65%–75%`, `<65%`, `<40%`). Click **Download Excel**' . ($showGazette ? ' or **Download Condonation & Detention Gazette (PDF)**.' : '.'),
                            '**2. Campus Timetable & Room Usage**: Open **Academic Views → Campus Timetable & Room Usage** to check classroom occupancy across buildings for any day and period.',
                            '**3. Faculty-wise Timetable**: Open **Academic Views → Faculty-wise Timetable** and select any department and faculty member to view their weekly schedule.',
                        ])),
                        'example'       => 'Generating the official semester Condonation & Detention Gazette PDF for each B.Tech class prior to hall-ticket issuance.',
                        'validations'   => [
                            'Uses centralized regulatory attendance thresholds configured for the active regulation.',
                        ],
                    ],
                ],
            ],
            [
                'id'          => 'acad-exams-dossier-custody-accounts',
                'title'       => 'Exams & Dossier: Results Publication, Student Profiles, Custody & Accounts',
                'icon'        => 'bi-file-earmark-spreadsheet',
                'color'       => 'warning',
                'description' => 'End-to-end semester examination results publication, CIA reconciliation audit, student dossier verification, physical certificate custody, and student password resets.',
                'stories'     => array_values(array_filter([
                    [
                        'id'            => 'acad-publish-exam-results',
                        'title'         => 'Create Exam Notifications, Import CSV Results, Audit CIA & Publish',
                        'icon'          => 'bi-file-earmark-arrow-up',
                        'module_key'    => 'MOD_RESULTS_PUBLISH',
                        'nav_path'      => 'Top Menu → Exams & Dossier → Publish Results',
                        'route_url'     => 'academicsectionresults.php',
                        'route_label'   => 'Open Results Manager',
                        'purpose'       => 'Manage the complete lifecycle of university semester examination results: notification creation, validated CSV preview/import, CIA discrepancy auditing, review sign-off, and publication to students and SEE attainment.',
                        'prerequisites' => [
                            'Examination Results module enabled.',
                        ],
                        'steps'         => array_values(array_filter([
                            'Open **Exams & Dossier → Publish Results**.',
                            '**Step 1 — Create Notification**: Click **New Exam Notification**, enter the **Notification Code**, **Title**, **Degree Program**, **Regulation**, **Academic Year**, **Year-Semester**, **Month & Year**, and **Exam Type** (`REGULAR`, `SUPPLEMENTARY`, or `REVALUATION`), and click **Save Notification**.',
                            '**Step 2 — Validate & Preview CSV**: Click **Upload CSV** on the notification row (while in `DRAFT` status), select **Import behavior** (`MERGE` to add/update rows or `REPLACE` to replace the entire draft), choose your `.csv` file (use **Sample CSV Template** for reference), and click **Validate & Preview Results**.',
                            '**Step 3 — Commit Preview**: Review the validated preview counts and sample rows on screen, then confirm the import.',
                            '**Step 4 — Audit CIA Discrepancies**: Click **Audit CIA** (or open the **CIA vs Exam Internal Reconciliation** tab) to compare portal-calculated CIA scores against uploaded CSV internal marks, filter by **Variances Only**, and export the audit CSV if needed.',
                            '**Step 5 — Review & Publish**: Enter verification observations and click **Mark reviewed** (`DRAFT` → `REVIEWED`), then click the green eye button to **Publish** results to students (which also synchronizes regular SEE marks for OBE attainment).',
                            $showCreditPromo ? '**Optional — Credit Promotion Simulation**: Open the **Credit Promotion Simulation (R23 Sec 18)** tab to run dry-run promotion eligibility checks and record committee overrides.' : null,
                        ])),
                        'example'       => 'Creating `BTECH_R23_3_1_NOV2026`, uploading the results CSV, verifying zero CIA discrepancies, marking it `REVIEWED`, and clicking **Publish**.',
                        'validations'   => [
                            'Check the **Course policy readiness** and **Import history** tabs on the **Publish Results** screen to verify subject evaluation rules and historical import batches.',
                        ],
                    ],
                    [
                        'id'            => 'acad-student-profiles-vault',
                        'title'         => 'Verify Student Profiles & Digital Document Vault',
                        'icon'          => 'bi-person-badge',
                        'module_key'    => 'MOD_STUDENT_PROFILE',
                        'nav_path'      => 'Top Menu → Exams & Dossier → Student Profiles & Vault',
                        'route_url'     => 'adminstudentprofiles.php',
                        'route_label'   => 'Open Student Profiles',
                        'purpose'       => 'Verify student biographical profiles, admission details, and uploaded credential scans in the digital document vault.',
                        'prerequisites' => [
                            'Student Profile module enabled.',
                        ],
                        'steps'         => [
                            'Open **Exams & Dossier → Student Profiles & Vault**.',
                            'Search by **Roll No** or filter by **Department** and **Class**, then click **View Dossier**.',
                            ['text' => 'Inspect each uploaded document via **View**, click **Verify** beside verified credentials, and click **Mark Profile Verified**.', 'requires_write' => true],
                        ],
                        'example'       => 'Verifying a student’s uploaded SSC and Intermediate certificates and marking their profile `Verified`.',
                        'validations'   => [
                            'Sensitive document verification is restricted to Academic Section, Admin, and SuperAdmin.',
                        ],
                    ],
                    [
                        'id'            => 'acad-custody-and-certificates',
                        'title'         => 'Manage Physical Custody Ledger & Issue Statutory Certificates',
                        'icon'          => 'bi-journal-bookmark',
                        'module_key'    => 'MOD_CERTIFICATES',
                        'nav_path'      => 'Top Menu → Exams & Dossier → Custody & Certificates',
                        'route_url'     => 'admincustodyledger.php',
                        'route_label'   => 'Open Custody Ledger',
                        'purpose'       => 'Log original certificates received at admission, track temporary withdrawals and permanent handovers, and approve/print/issue student certificate applications.',
                        'prerequisites' => [
                            'Certificates & Custody module enabled.',
                        ],
                        'steps'         => [
                            'Open **Exams & Dossier → Custody & Certificates**.',
                            ['text' => 'In **Physical Custody Ledger**, use **Receive Original Document** to register deposited certificates, and use **Checkout**, **Check In**, or **Exit Return** to track custody movements.', 'requires_write' => true],
                            ['text' => 'In **Statutory Certificate Requests**, review student requests (`CUSTODIAL`, `BONAFIDE`, `STUDY_CONDUCT`, `TRANSFER_CERTIFICATE`, `NO_DUES`), click **Approve**, click **Print**, and click **Issue**.', 'requires_write' => true],
                        ],
                        'example'       => 'Recording a student’s Original SSC Memo into `Almirah 2, Shelf B` and issuing a Custodial Certificate.',
                        'validations'   => [
                            'All custody transitions and certificate status changes are logged with user attribution and timestamps.',
                        ],
                    ],
                    ($showSeeMarks || $showAiAssist) ? [
                        'id'            => 'acad-attainment-import-ai',
                        'title'         => 'Import Attainment Evidence & Review AI Drafts',
                        'icon'          => 'bi-cloud-arrow-up',
                        'module_key'    => $showSeeMarks ? 'MOD_SEE_MARKS' : 'MOD_AI_ASSISTANCE',
                        'nav_path'      => 'Academic Section Home → Import attainment evidence / Review AI drafts',
                        'route_url'     => $showSeeMarks ? 'attainmentimport.php' : 'aiassistance.php',
                        'route_label'   => 'Open Tool',
                        'purpose'       => 'Import external attainment evidence CSVs or inspect AI draft queues.',
                        'prerequisites' => [
                            'Respective external attainment or AI assistance feature enabled.',
                        ],
                        'steps'         => array_values(array_filter([
                            $showSeeMarks ? 'Use **Import attainment evidence** to preview and import external course attainment CSVs.' : null,
                            $showAiAssist ? 'Use **Review AI drafts** to inspect queued AI draft summaries.' : null,
                        ])),
                        'example'       => 'Previewing an external attainment batch import under **Import attainment evidence**.',
                        'validations'   => [
                            'Maintains full file integrity verification and audit snapshots.',
                        ],
                    ] : null,
                    [
                        'id'            => 'acad-account-actions',
                        'title'         => 'Reset Student Passwords & Update Your Account Password',
                        'icon'          => 'bi-key',
                        'module_key'    => null,
                        'nav_path'      => 'Top Menu → Account Actions → Reset Student Pwd / Change Password',
                        'route_url'     => 'academicsectionresetstudentpwd.php',
                        'route_label'   => 'Reset Student Password',
                        'purpose'       => 'Assist students who cannot sign in to the Student Portal by resetting their password, and maintain your own Academic Section password.',
                        'prerequisites' => [
                            'Student Roll Number / Username.',
                        ],
                        'steps'         => [
                            'Open **Account Actions → Reset Student Pwd**, enter the student’s Roll Number, set the new password with justification remarks, and submit.',
                            'Note: Academic Section resets **Student** passwords only (Faculty password resets are performed by HODs or Principal/Admin).',
                            'Use **Account Actions → Change Password** to update your own password.',
                        ],
                        'example'       => 'Resetting a student’s password during feedback or results viewing week.',
                        'validations'   => [
                            'All student password resets are recorded in the institutional audit log.',
                        ],
                    ],
                ])),
            ],
        ];
    }

    // =========================================================================
    // 5. SUPERADMIN CATALOG
    // =========================================================================
    private function buildSuperAdminCatalog(array $reg): array
    {
        return [
            [
                'id'          => 'superadmin-structure-masters',
                'title'       => 'Part 1: Academic Structure Masters (Departments, Programs & Branches)',
                'icon'        => 'bi-diagram-3',
                'color'       => 'primary',
                'description' => 'Foundational institutional hierarchy: managing academic departments, degree programs, branch specializations, and the unified academic directory.',
                'stories'     => [
                    [
                        'id'            => 'superadmin-departments-programs-specs',
                        'title'         => 'Configure Departments, Degree Programs & Specializations',
                        'icon'          => 'bi-building',
                        'module_key'    => null,
                        'nav_path'      => 'Top Menu → Structure → Departments / Degree Programs / Specializations / Academic Directory',
                        'route_url'     => 'superadmindepts.php',
                        'route_label'   => 'Manage Structure',
                        'purpose'       => 'Define and maintain the core organizational units (Departments, Degree Programs, and Specializations) that underpin all classes, curricula, and user accounts.',
                        'prerequisites' => [
                            'Authenticated SuperAdmin session.',
                        ],
                        'steps'         => [
                            '**1. Departments**: Open **Structure → Departments** to add or edit academic departments (Short Name, Full Name, Username/HOD login, and Active status).',
                            '**2. Degree Programs**: Open **Structure → Degree Programs** to configure UG/PG programs (`B.Tech`, `M.Tech`, `MCA`, etc., including Program Code, Short Name, Full Name, and Program Level).',
                            '**3. Specializations / Branches**: Open **Structure → Specializations / Branches** to link each branch (e.g., `CSE`, `ECE`, `AI & ML`) to its parent **Department** and **Degree Program**.',
                            '**4. Unified Directory**: Open **Structure → Academic Directory** to inspect the complete Department → Program → Specialization mapping tree on one screen.',
                        ],
                        'example'       => 'Creating a new B.Tech specialization `CSE (Artificial Intelligence)` under the Department of CSE in **Structure → Specializations / Branches**.',
                        'validations'   => [
                            'Specializations connect semester classes and curriculum subjects to their owning department and degree program.',
                        ],
                    ],
                ],
            ],
            [
                'id'          => 'superadmin-classes-batches-obe',
                'title'       => 'Part 2: Classes, Student Batches, Bell Timings & Full-Cycle OBE Hierarchy',
                'icon'        => 'bi-journal-bookmark',
                'color'       => 'success',
                'description' => 'Bulk and individual class setup, permanent student cohorts, bell timing templates, PO/PSO definitions, and Master Vision/Mission/PEO/PO OBE governance.',
                'stories'     => [
                    [
                        'id'            => 'superadmin-classes-and-timings',
                        'title'         => 'Manage Batch Program Classes, Individual Classes & Bell Timings',
                        'icon'          => 'bi-grid-3x3',
                        'module_key'    => null,
                        'nav_path'      => 'Top Menu → Classes & OBE → Batch Program Classes / Individual Section Classes / Bell Timings',
                        'route_url'     => 'superadminprogramclasses.php',
                        'route_label'   => 'Manage Classes',
                        'purpose'       => 'Create semester class cohorts across specializations (in bulk or individually) and assign their Academic Year, Regulation, Student Batch, Start/End Dates, and Bell Timing Template.',
                        'prerequisites' => [
                            'Academic Years, Regulations, Specializations, and Bell Timing Templates defined.',
                        ],
                        'steps'         => [
                            '**1. Bell Timings & Hour Templates**: Open **Classes & OBE → Bell Timings** to define bell schedule templates with Period numbers, descriptions, and start/end times.',
                            '**2. Batch Program Classes — Bulk Setup**: Open **Classes & OBE → Batch Program Classes**, select a Program, Academic Year, Regulation, and Semester to bulk-create or update class sections across multiple branches simultaneously.',
                            '**3. Individual Section Classes**: Open **Classes & OBE → Individual Section Classes** to add or edit individual class sections (Class Name, Year-Sem, Section, Start Date, End Date, Regulation, Bell Timing Template, Student Batch, and Active Status).',
                        ],
                        'example'       => 'Setting the classwork start and end dates for all `III B.Tech I Sem` sections in **Batch Program Classes**.',
                        'validations'   => [
                            'Class start and end dates strictly govern when faculty can mark daily attendance and when post-semester bulk tools activate.',
                        ],
                    ],
                    [
                        'id'            => 'superadmin-batches-and-obe-cockpit',
                        'title'         => 'Configure Student Batches & Master OBE Governance (Vision → Mission → PEO → PO)',
                        'icon'          => 'bi-diagram-3-fill',
                        'module_key'    => 'MOD_BATCH_GOVERNANCE',
                        'nav_path'      => 'Top Menu → Classes & OBE → Student Batches / Batch OBE / POs & PSOs',
                        'route_url'     => 'superadminbatchobe.php',
                        'route_label'   => 'Open OBE Cockpit',
                        'purpose'       => 'Manage permanent admission-to-graduation student cohorts, define Program Outcomes (`PO1–PO12`) and `PSOs`, and maintain the Master Vision, Mission, PEO, and Longitudinal OBE Attainment hierarchy.',
                        'prerequisites' => [
                            'Departments, Programs, and Regulations configured.',
                        ],
                        'steps'         => [
                            '**1. Program Outcomes & PSOs**: Open **Classes & OBE → POs & PSOs** to configure the 12 NBA Graduate Attributes (`PO1–PO12`) and department-specific `PSOs` (`PSO1–PSO4`) per regulation and specialization.',
                            '**2. Student Batches**: Open **Classes & OBE → Student Batches** to create multi-year student cohorts (e.g., `2023–2027 B.Tech R23 Batch`) and map corresponding semester classes to the cohort.',
                            '**3. Master OBE Governance Cockpit**: Open **Classes & OBE → Batch OBE**, select an **Academic Department**, and choose either **⭐ Department Master Standards** (stored once, inherited by all batches) or a specific **Student Cohort**:',
                            '• **Tab 1 (Vision & Mission)**: Define the Department Vision and Mission statements (`M1`, `M2`, `M3`...) with an effective admission year.',
                            '• **Tab 2 (PEOs)**: Define Master Program Educational Objectives (`PEO1`..`PEO4`) and target scores (or set cohort-specific target overrides).',
                            '• **Tab 3 & Tab 4 (PEO → Mission & PO/PSO → PEO Matrices)**: Configure correlation weights (`1`, `2`, `3`) linking PEOs to Missions and POs/PSOs to PEOs.',
                            '• **Tabs 5–7 (Cohort Macro Attainment, NBA Table 3.2 & 3.3.2 Program Articulation, and Longitudinal Quality Analytics)**: Inspect multi-semester program articulation averages, blended 80%/20% PO attainment, and multi-cohort trajectory charts.',
                        ],
                        'example'       => 'Defining Master Vision, Mission, and PEO statements once for CSE under **Batch OBE** so all CSE student batches inherit them automatically.',
                        'validations'   => [
                            'Master OBE statements are stored once per department and effective year without duplicating records across individual batches.',
                        ],
                    ],
                ],
            ],
            [
                'id'          => 'superadmin-policy-and-regulations',
                'title'       => 'Part 3: Academic Years, Regulations, Term Settings & Attainment Profiles',
                'icon'        => 'bi-sliders',
                'color'       => 'info',
                'description' => 'Configure academic years, autonomous degree regulations, course categories/types, active regulatory settings, attendance rules, and attainment calculation profiles.',
                'stories'     => [
                    [
                        'id'            => 'superadmin-regulations-and-years',
                        'title'         => 'Manage Academic Years & Autonomous Regulations (Categories & Course Types)',
                        'icon'          => 'bi-journal-text',
                        'module_key'    => null,
                        'nav_path'      => 'Top Menu → Policy & Terms → Academic Years / Academic Regulations',
                        'route_url'     => 'superadminregulations.php',
                        'route_label'   => 'Manage Regulations',
                        'purpose'       => 'Maintain active academic year sessions and define autonomous regulations (`R19`, `R20`, `R23`, `R25`) including credit ceilings, course categories, and course evaluation schemes.',
                        'prerequisites' => [
                            'Degree Programs configured.',
                        ],
                        'steps'         => [
                            '**1. Academic Years**: Open **Policy & Terms → Academic Years** to add academic year labels (e.g., `2026-2027`) and toggle their active status.',
                            '**2. Academic Regulations**: Open **Policy & Terms → Academic Regulations** to create or select a regulation for a program, and configure its **Duration**, **Total Degree Credits** (e.g., `160`), **Lateral Entry / Honors / Minor Credits**, **Course Categories** (`PC`, `PE`, `OE`, `BS`, `ES`, `HS`, `MC`, `SC`, `PR` with min/max credit %), and **Course Types** (`Theory`, `Lab`, `Integrated`, `Project`, etc. with CIE + SEE max marks).',
                        ],
                        'example'       => 'Configuring `R23` B.Tech regulation details with `30 CIE + 70 SEE = 100 Total` for Theory and `30 CIE + 70 SEE` for Labs.',
                        'validations'   => [
                            'Course Categories and Course Types configured here drive the statutory compliance checks in the **Course Structure** manager.',
                        ],
                    ],
                    [
                        'id'            => 'superadmin-academic-and-attainment-settings',
                        'title'         => 'Configure Active Regulation Settings, Attendance Rules & Attainment Profiles',
                        'icon'          => 'bi-gear-wide-connected',
                        'module_key'    => null,
                        'nav_path'      => 'Top Menu → Policy & Terms → Active Term Settings / Attendance Rules / Attainment Profiles',
                        'route_url'     => 'superadminacademicsettings.php',
                        'route_label'   => 'Open Academic Settings',
                        'purpose'       => 'Tune centralized regulatory parameters per regulation (CIA mid weights, attendance thresholds, OBE attainment levels, grade bands) and configure subject-type or per-course attainment policy overrides.',
                        'prerequisites' => [
                            'Regulations defined under **Academic Regulations**.',
                        ],
                        'steps'         => [
                            '**1. Active Term Settings**: Open **Policy & Terms → Active Term Settings** and select a specific Regulation to view and edit settings grouped by category (`CIA`, `SEE`, `ATTENDANCE`, `ATTAINMENT`, `GRADING`, `GENERAL`), such as Better/Lesser Mid weightings (`80%` / `20%`), Minimum Aggregate Attendance (`75%`), Condonation Floor (`65%`), Subject Minimum (`40%`), and Bloom cognitive balance rules.',
                            '**2. Attendance & Condonation Rules**: Open **Policy & Terms → Attendance Rules** to review or update regulation-specific attendance rule operators and brackets.',
                            '**3. Attainment Profiles & Course Overrides**: Open **Policy & Terms → Attainment Profiles**, select a **Regulation**, **Subject type** (or a specific **Course override**), configure maximum marks, **CO Target Percentage**, **Attainment Level 1/2/3 Thresholds**, and **Grade Strategy** (`NONE`, `LOWER_BOUND`, `MIDPOINT`, `UPPER_BOUND`), and click **Save profile**.',
                        ],
                        'example'       => 'Setting `CO Target Percentage = 60%` and Level 1/2/3 thresholds (`50% / 60% / 70%`) for R23 Theory courses under **Attainment Profiles**.',
                        'validations'   => [
                            'Every change in **Active Term Settings** is permanently recorded in the settings audit log with a versioned policy snapshot.',
                        ],
                    ],
                ],
            ],
            [
                'id'          => 'superadmin-governance-features-ai-admins',
                'title'       => 'Part 4: System Governance, Feature Toggles, Role Permissions & AI Providers',
                'icon'        => 'bi-shield-lock',
                'color'       => 'warning',
                'description' => 'Control global feature switches, role-level visibility (Visible, Read-Only, Hidden), fine-grained role action permissions, optional AI providers, and administrator accounts.',
                'stories'     => [
                    [
                        'id'            => 'superadmin-feature-toggles-and-permissions',
                        'title'         => 'Manage Global Feature Toggles, Role Visibility & Action Permissions',
                        'icon'          => 'bi-toggles',
                        'module_key'    => null,
                        'nav_path'      => 'Top Menu → Governance → Feature Toggles & Role Matrix',
                        'route_url'     => 'superadminfeatures.php',
                        'route_label'   => 'Open Feature Toggles',
                        'purpose'       => 'Centrally enable or disable system modules globally, set runtime visibility (`Visible`, `Hidden`, `Read-Only`) for Faculty, Students, and HODs, and configure granular action permissions (`view`, `edit`, `approve`, `export`) without code changes.',
                        'prerequisites' => [
                            'SuperAdmin authorization.',
                        ],
                        'steps'         => [
                            'Open **Governance → Feature Toggles & Role Matrix**.',
                            'In the **Feature Management & Visibility Matrix** form, review modules grouped by category (`ATTENDANCE`, `CIA`, `OBE`, `EXAM`, `ACCREDITATION`, `STUDENT_PORTAL`, `GENERAL`):',
                            '• Toggle **Global Status** (`Enabled` / `Disabled`). When disabled globally, the feature is turned off across all non-superadmin users and hidden from their dynamic User Manuals.',
                            '• Set **Faculty Access** (`Visible`, `Hidden`, or `Read-Only`), **Student Access** (`Visible` or `Hidden`), and **HOD Access** (`Visible`, `Hidden`, or `Read-Only`).',
                            '• Click **Save All Changes** in the sticky bottom bar.',
                            'In the **Role action permissions** card at the bottom of the page, select a **Feature**, **Role** (`faculty`, `hod`, `admin`, `academic_section`, `student`), **Action** (`view`, `edit`, `approve`, `export`), and **Permission** (`Deny` or `Allow within feature visibility`), then click **Save permission**.',
                        ],
                        'example'       => 'Setting **CIA Marks** to `Read-Only` for Faculty after the internal marks submission deadline so instructors can still view and export marks but cannot modify them.',
                        'validations'   => [
                            'Module Dependencies: The system automatically enforces parent feature dependencies (for example, Optional AI Assistance requires SEE Marks; Course Master File requires Lesson Plan; CQI Action Tracking requires CO-PO Mapping and OBE Analysis; Lab Rubrics requires CIA Marks; Certificate QR Verification requires Certificates and Student Profile).',
                            'All role User Manuals (Faculty, HOD, Principal/Admin, and Academic Section) and their PDF downloads automatically adapt in real time to your settings here.',
                        ],
                    ],
                    [
                        'id'            => 'superadmin-ai-providers-and-admins',
                        'title'         => 'Configure Optional AI Providers, Manage System Admins & Account Security',
                        'icon'          => 'bi-robot',
                        'module_key'    => null,
                        'nav_path'      => 'Top Menu → Governance → System Administrators / AI Providers / Change Password',
                        'route_url'     => 'superadminadmins.php',
                        'route_label'   => 'Manage Admins',
                        'purpose'       => 'Configure local Ollama or OpenAI-compatible providers for optional AI drafts, manage Principal and Academic Section administrator accounts, and update your SuperAdmin password.',
                        'prerequisites' => [
                            'SuperAdmin authorization.',
                        ],
                        'steps'         => [
                            '**1. System Administrators**: Open **Governance → System Administrators** to add, edit, or reset passwords for Principal Office (`admin`) and `academic_section` accounts.',
                            '**2. Optional AI Providers**: Click **AI providers** on **SuperAdmin Home**, select **Local Ollama** or **API (Chat Completions)**, enter the **Base URL**, **Model identifier**, **Credential environment variable** name (API keys are read from the server environment and never stored in the database), and **Timeout**, tick **Enable this provider**, and click **Save provider**.',
                            '**3. Direct Institutional Dossier Access**: Use the **Governance** dropdown links to access **Student Profiles & Vault**, **Custody & Certificates Ledger**, and **Exam Results Publication**.',
                            '**4. SuperAdmin Password**: Open **Governance → Change Password** to update your SuperAdmin password periodically and click **Logout** when finished.',
                        ],
                        'example'       => 'Registering a local Ollama model under **AI providers** before enabling Optional AI Assistance in **Feature Toggles**.',
                        'validations'   => [
                            'All SuperAdmin configuration changes and permission updates are permanently recorded in the institutional audit and activity logs.',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Format inline markdown (`**bold**` and `` `code` ``) into clean HTML for web view.
     */
    public static function formatWebText(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/`([^`]+)`/', '<code class="bg-light px-1 rounded border text-primary-emphasis">$1</code>', $escaped) ?? $escaped;
        return $escaped;
    }

    /**
     * Format inline markdown (`**bold**` and `` `code` ``) into clean HTML for mPDF.
     */
    public static function formatPdfText(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/`([^`]+)`/', '<span style="font-family: monospace; background-color: #f1f5f9; padding: 1px 4px; color: #0f172a;">$1</span>', $escaped) ?? $escaped;
        return $escaped;
    }

    /**
     * Generate and stream (or return) the role-specific mPDF User Manual.
     *
     * @param string $role Role identifier ('faculty', 'hod', 'admin', 'academic_section', 'superadmin')
     * @param bool $download True for attachment download ('D'), false for inline browser PDF view ('I')
     * @param bool $returnString True in CLI tests to return raw PDF binary string ('S')
     * @return string|null
     */
    public function renderPdfForRole(string $role = 'faculty', bool $download = false, bool $returnString = false): ?string
    {
        require_once __DIR__ . '/../vendor/autoload.php';

        $manual = $this->getManualForRole($role);
        $roleSlug = preg_replace('/[^a-z0-9_]/i', '_', $manual['role']);

        $tmpDir = __DIR__ . '/../uploads/tmp';
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0777, true);
        }

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'margin_left'       => 12,
            'margin_right'      => 12,
            'margin_top'        => 24,
            'margin_bottom'     => 16,
            'margin_header'     => 8,
            'margin_footer'     => 8,
            'default_font_size' => 9.5,
            'default_font'      => 'sans-serif',
            'tempDir'           => $tmpDir,
        ]);

        $mpdf->SetTitle('JNTUACEA - ' . $manual['portal_title']);
        $mpdf->SetAuthor('JNTUA College of Engineering Ananthapuramu');
        $mpdf->aliasNbPages();

        $headerHtml = '
        <table width="100%" style="border-bottom: 1px solid #cbd5e1; font-size: 8px; color: #475569; padding-bottom: 4px;">
            <tr>
                <td width="65%" style="text-align: left;">
                    <strong>JNTUACEA — Student Academic Record Book (SARB)</strong> | ' . htmlspecialchars($manual['portal_title']) . '
                </td>
                <td width="35%" style="text-align: right;">
                    Dynamic Edition (' . htmlspecialchars($manual['generated_at']) . ')
                </td>
            </tr>
        </table>';

        $footerHtml = '
        <table width="100%" style="border-top: 1px solid #cbd5e1; font-size: 8px; color: #64748b; padding-top: 4px;">
            <tr>
                <td width="70%" style="text-align: left;">
                    Role: ' . htmlspecialchars($manual['role_label']) . ' — Generated dynamically from active institutional feature settings
                </td>
                <td width="30%" style="text-align: right;">
                    Page {PAGENO} of {nbpg}
                </td>
            </tr>
        </table>';

        $mpdf->SetHTMLHeader($headerHtml);
        $mpdf->SetHTMLFooter($footerHtml);

        $logoPath = realpath(__DIR__ . '/../images/jntuacea.png');
        $logoImg = ($logoPath && file_exists($logoPath))
            ? '<img src="' . $logoPath . '" style="height: 68px; margin-bottom: 6px;" />'
            : '';

        $styles = '
        <style>
            body { font-family: sans-serif; color: #1e293b; line-height: 1.45; }
            .cover-box { border: 2px solid #1a365d; background-color: #f8fafc; padding: 18px; text-align: center; margin-bottom: 14px; border-radius: 6px; }
            .inst-title { font-size: 15px; font-weight: bold; color: #1a365d; margin: 4px 0 2px 0; }
            .inst-sub { font-size: 10px; color: #475569; margin: 0 0 10px 0; }
            .manual-main-title { font-size: 18px; font-weight: bold; color: #0d6efd; margin: 6px 0 4px 0; }
            .toc-table { width: 100%; border-collapse: collapse; font-size: 9px; margin-bottom: 12px; }
            .toc-table th { background-color: #1a365d; color: #ffffff; padding: 6px 8px; text-align: left; }
            .toc-table td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; }
            .cat-banner { background-color: #1a365d; color: #ffffff; padding: 8px 12px; font-size: 12px; font-weight: bold; margin-top: 12px; margin-bottom: 6px; border-radius: 4px; }
            .story-block { border: 1px solid #cbd5e1; border-radius: 5px; padding: 10px 12px; margin-bottom: 11px; page-break-inside: avoid; background-color: #ffffff; }
            .story-title-table { width: 100%; border-collapse: collapse; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin-bottom: 6px; }
            .story-num { background-color: #0d6efd; color: #ffffff; font-weight: bold; font-size: 10px; padding: 3px 7px; border-radius: 3px; }
            .story-heading { font-size: 11.5px; font-weight: bold; color: #0f172a; padding-left: 6px; }
            .badge-active { background-color: #dcfce7; color: #166534; font-size: 8px; font-weight: bold; padding: 2px 6px; border-radius: 3px; }
            .badge-readonly { background-color: #fef3c7; color: #92400e; font-size: 8px; font-weight: bold; padding: 2px 6px; border-radius: 3px; }
            .nav-path-bar { background-color: #f1f5f9; border-left: 3px solid #0d6efd; padding: 4px 8px; font-size: 8.8px; margin-bottom: 6px; color: #1e293b; }
            .section-label { font-size: 9px; font-weight: bold; color: #1a365d; margin-top: 5px; margin-bottom: 2px; }
            .example-box { background-color: #f0fdf4; border-left: 3px solid #16a34a; padding: 5px 8px; font-size: 8.6px; margin-top: 5px; }
            .rules-box { background-color: #fffbeb; border-left: 3px solid #d97706; padding: 5px 8px; font-size: 8.5px; margin-top: 5px; }
            .readonly-box { background-color: #fff3cd; border: 1px solid #ffecb5; color: #664d03; padding: 5px 8px; font-size: 8.5px; margin-bottom: 6px; }
            ol.steps-ol { margin: 2px 0 4px 0; padding-left: 18px; font-size: 9px; }
            ol.steps-ol li { margin-bottom: 3px; }
            ul.compact-ul { margin: 2px 0 4px 0; padding-left: 16px; font-size: 8.6px; }
            ul.compact-ul li { margin-bottom: 2px; }
        </style>';

        $coverAndTocHtml = $styles . '
        <div class="cover-box">
            ' . $logoImg . '
            <div class="inst-title">JNTUA COLLEGE OF ENGINEERING (AUTONOMOUS) ANANTHAPURAMU</div>
            <div class="inst-sub">(Accredited by NAAC with "A" Grade | Constituent College of JNTUA)</div>
            <div class="manual-main-title">STUDENT ACADEMIC RECORD BOOK (SARB)</div>
            <div style="font-size: 13px; font-weight: bold; color: #198754; margin-bottom: 8px; text-transform: uppercase;">
                ' . htmlspecialchars($manual['portal_title']) . '
            </div>
            <div style="font-size: 8.8px; color: #334155;">
                <strong>Target Role:</strong> ' . htmlspecialchars($manual['role_label']) . ' &nbsp;|&nbsp;
                <strong>Enabled Workflows:</strong> ' . (int)$manual['total_stories'] . ' User Stories across ' . (int)$manual['total_categories'] . ' Categories &nbsp;|&nbsp;
                <strong>Generated On:</strong> ' . htmlspecialchars($manual['generated_at']) . '
            </div>
        </div>

        <div style="background-color: #eff6ff; border: 1px solid #bfdbfe; padding: 8px 10px; font-size: 8.8px; margin-bottom: 10px; border-radius: 4px;">
            <strong>About This Dynamic Manual:</strong> ' . htmlspecialchars($manual['subtitle']) . ' It includes step-by-step instructions <em>only</em> for the modules, menu items, and links currently enabled for this role.
        </div>

        <h4 style="color: #1a365d; margin: 6px 0 6px 0; font-size: 11px; text-transform: uppercase;">
            Table of Contents (' . htmlspecialchars($manual['role_label']) . ' Workflows)
        </h4>
        <table class="toc-table">
            <thead>
                <tr>
                    <th style="width: 7%;">#</th>
                    <th style="width: 45%;">Workflow / User Story Title</th>
                    <th style="width: 35%;">Menu Navigation Path</th>
                    <th style="width: 13%; text-align: center;">Access Mode</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($manual['categories'] as $cat) {
            $coverAndTocHtml .= '
                <tr style="background-color: #e2e8f0; font-weight: bold;">
                    <td colspan="4" style="color: #1a365d; padding: 5px 8px;">
                        Part ' . htmlspecialchars($cat['Roman']) . ': ' . htmlspecialchars($cat['title']) . '
                    </td>
                </tr>';
            foreach ($cat['stories'] as $story) {
                $modeLabel = ($story['access_mode'] === 'READONLY') ? 'Read-Only' : 'Full Access';
                $coverAndTocHtml .= '
                <tr>
                    <td style="font-weight: bold; color: #0d6efd;">' . (int)$story['number'] . '</td>
                    <td>' . htmlspecialchars($story['title']) . '</td>
                    <td style="font-size: 8px; color: #475569;">' . htmlspecialchars($story['nav_path'] ?? '') . '</td>
                    <td style="text-align: center; font-size: 8px;">' . $modeLabel . '</td>
                </tr>';
            }
        }

        $coverAndTocHtml .= '
            </tbody>
        </table>';

        $mpdf->Bookmark('Cover & Table of Contents', 0);
        $mpdf->WriteHTML($coverAndTocHtml);

        foreach ($manual['categories'] as $cat) {
            $mpdf->AddPage();
            $catBookmarkTitle = 'Part ' . $cat['Roman'] . ': ' . $cat['title'];
            $mpdf->Bookmark($catBookmarkTitle, 0);

            $catHtml = $styles . '
            <div class="cat-banner">
                PART ' . htmlspecialchars($cat['Roman']) . ': ' . htmlspecialchars(strtoupper($cat['title'])) . '
            </div>
            <p style="font-size: 8.8px; color: #475569; margin: 0 0 8px 0;">' . htmlspecialchars($cat['description']) . '</p>';

            $mpdf->WriteHTML($catHtml);

            foreach ($cat['stories'] as $story) {
                $mpdf->Bookmark($story['number'] . '. ' . $story['title'], 1);

                $badgeHtml = ($story['access_mode'] === 'READONLY')
                    ? '<span class="badge-readonly">READ-ONLY</span>'
                    : '<span class="badge-active">ACTIVE</span>';

                $storyHtml = $styles . '
                <div class="story-block">
                    <table class="story-title-table">
                        <tr>
                            <td style="width: 82%;">
                                <span class="story-num">' . (int)$story['number'] . '</span>
                                <span class="story-heading">' . htmlspecialchars($story['title']) . '</span>
                            </td>
                            <td style="width: 18%; text-align: right;">
                                ' . $badgeHtml . '
                            </td>
                        </tr>
                    </table>';

                if (!empty($story['readonly_notice'])) {
                    $storyHtml .= '<div class="readonly-box"><strong>Read-Only Notice:</strong> ' . htmlspecialchars($story['readonly_notice']) . '</div>';
                }

                $storyHtml .= '
                    <div class="nav-path-bar">
                        <strong>Where to Click:</strong> ' . htmlspecialchars($story['nav_path']) . '
                    </div>
                    <div style="font-size: 9px; margin-bottom: 4px;">
                        <strong>Purpose:</strong> ' . self::formatPdfText($story['purpose']) . '
                    </div>';

                if (!empty($story['prerequisites'])) {
                    $storyHtml .= '<div class="section-label">Prerequisites:</div><ul class="compact-ul">';
                    foreach ($story['prerequisites'] as $prereq) {
                        $storyHtml .= '<li>' . self::formatPdfText($prereq) . '</li>';
                    }
                    $storyHtml .= '</ul>';
                }

                $storyHtml .= '<div class="section-label">Step-by-Step Instructions:</div><ol class="steps-ol">';
                foreach ($story['steps'] as $stepText) {
                    $storyHtml .= '<li>' . self::formatPdfText($stepText) . '</li>';
                }
                $storyHtml .= '</ol>';

                if (!empty($story['example'])) {
                    $storyHtml .= '
                    <div class="example-box">
                        <strong>Practical Example:</strong> ' . self::formatPdfText($story['example']) . '
                    </div>';
                }

                if (!empty($story['validations'])) {
                    $storyHtml .= '<div class="rules-box"><strong>System Rules &amp; Checks:</strong><ul class="compact-ul">';
                    foreach ($story['validations'] as $valRule) {
                        $storyHtml .= '<li>' . self::formatPdfText($valRule) . '</li>';
                    }
                    $storyHtml .= '</ul></div>';
                }

                $storyHtml .= '</div>';

                $mpdf->WriteHTML($storyHtml);
            }
        }

        if ($returnString) {
            return $mpdf->Output('', 'S');
        }

        $filename = 'JNTUACEA_' . ucfirst($roleSlug) . '_User_Manual_' . date('Ymd') . '.pdf';
        $dest = $download ? 'D' : 'I';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $mpdf->Output($filename, $dest);
        exit();
    }

    /**
     * Convert a positive integer to Roman numerals for category headings.
     */
    private function toRoman(int $num): string
    {
        $map = [
            'X'  => 10,
            'IX' => 9,
            'V'  => 5,
            'IV' => 4,
            'I'  => 1,
        ];
        $result = '';
        foreach ($map as $roman => $value) {
            $matches = intdiv($num, $value);
            $result .= str_repeat($roman, $matches);
            $num %= $value;
        }
        return $result ?: (string)$num;
    }
}
