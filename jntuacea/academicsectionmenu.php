<div class="container dontprint">
    <div class="row g-1">

        <div class="col-6 col-md-2">
            <a href="academicsectionhome.php"
                class="w-100 btn <?php echo (!empty($page_title) && $page_title == 'Home') ? 'btn-success' : 'btn-outline-primary'; ?>">
                <i class="bi bi-house-door me-1"></i> Home
            </a>
        </div>

        <div class="col-6 col-md-2">
            <div class="dropdown w-100">
                <button class="w-100 btn <?php echo (!empty($page_title) && in_array($page_title, ['Manage Buildings', 'Regulations', 'Course Structure', 'Syllabus', 'Curriculum Subjects'])) ? 'btn-success' : 'btn-outline-primary'; ?> dropdown-toggle"
                    type="button"
                    id="academicSetupDropdown"
                    data-bs-toggle="dropdown"
                    aria-expanded="false">
                    <i class="bi bi-journal-text me-1"></i> Academic Setup
                </button>
                <ul class="dropdown-menu w-100" aria-labelledby="academicSetupDropdown">
                    <li>
                        <a class="dropdown-item" href="academicsectionmanagebuildings.php">
                            <i class="bi bi-building me-1"></i> Buildings & Halls
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="academicsectionregulations.php">
                            <i class="bi bi-diagram-3 me-1"></i> Regulations
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="academicsectioncoursestructure.php">
                            <i class="bi bi-diagram-2 me-1"></i> Course Structure
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="academicsectioncurriculumsubjects.php">
                            <i class="bi bi-book me-1"></i> Curriculum Subjects
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="academicsectionsyllabus.php">
                            <i class="bi bi-file-earmark-text me-1"></i> Syllabus
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="col-6 col-md-2">
            <div class="dropdown w-100">
                <button class="w-100 btn <?php echo (!empty($page_title) && in_array($page_title, ['View Attendance'])) ? 'btn-success' : 'btn-outline-primary'; ?> dropdown-toggle"
                    type="button"
                    id="academicViewsDropdown"
                    data-bs-toggle="dropdown"
                    aria-expanded="false">
                    <i class="bi bi-eye me-1"></i> Academic Views
                </button>
                <ul class="dropdown-menu w-100" aria-labelledby="academicViewsDropdown">
                    <li>
                        <a class="dropdown-item" href="academicsectionshowallclsattendance.php">
                            <i class="bi bi-calendar-check me-1"></i> View Attendance
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="faculty_weekly_timetable.php">
                            <i class="bi bi-table me-1"></i> Faculty-wise Timetable
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="col-6 col-md-2">
            <div class="dropdown w-100">
                <button class="w-100 btn <?php echo (!empty($page_title) && in_array($page_title, ['Publish Results', 'Student Profiles & Dossier', 'Certificates Ledger', 'Examination Results'])) ? 'btn-success' : 'btn-outline-primary'; ?> dropdown-toggle"
                    type="button"
                    id="examResultsDropdown"
                    data-bs-toggle="dropdown"
                    aria-expanded="false">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Exams & Dossier
                </button>
                <ul class="dropdown-menu w-100" aria-labelledby="examResultsDropdown">
                    <li>
                        <a class="dropdown-item" href="academicsectionresults.php">
                            <i class="bi bi-file-earmark-arrow-up me-1"></i> Publish Results
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="adminstudentprofiles.php">
                            <i class="bi bi-person-badge me-1"></i> Student Profiles & Vault
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="admincustodyledger.php">
                            <i class="bi bi-journal-bookmark me-1"></i> Custody & Certificates
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="col-6 col-md-2">
            <div class="dropdown w-100">
                <button class="w-100 btn <?php echo (!empty($page_title) && in_array($page_title, ['Reset Student Pwd', 'Reset Faculty Pwd', 'Change Pwd'])) ? 'btn-success' : 'btn-outline-primary'; ?> dropdown-toggle"
                    type="button"
                    id="accountActionsDropdown"
                    data-bs-toggle="dropdown"
                    aria-expanded="false">
                    <i class="bi bi-key me-1"></i> Account Actions
                </button>
                <ul class="dropdown-menu w-100" aria-labelledby="accountActionsDropdown">
                    <li>
                        <a class="dropdown-item" href="academicsectionresetstudentpwd.php">
                            <i class="bi bi-person me-1"></i> Reset Student Pwd
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="academicsectionresetfacultypwd.php">
                            <i class="bi bi-briefcase me-1"></i> Reset Faculty Pwd
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="academicsectionchgpwd.php">
                            <i class="bi bi-shield-lock me-1"></i> Change Password
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="col-6 col-md-2">
            <a href="logout.php" class="w-100 btn btn-outline-danger">
                <i class="bi bi-box-arrow-right me-1"></i> Logout
            </a>
        </div>

    </div>
</div>
<br />