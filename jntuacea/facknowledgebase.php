<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "";
require_once("facheader.php");
?>
<div class="container">
    <div class="card my-4">
        <div class="card-header bg-secondary text-white">
            Knowledge Base
        </div>
        <div class="card-body">
            <p class="card-text">Let's make some of the key terms clear.</p>
            <ul class="card-text">
                <li><strong>Mission and Vision:</strong>
                    <p><strong>Definition:</strong> Mission and Vision Statement: Mission statements are essentially the means to achieve the vision of the institution. For example, if the vision is to create high-quality engineering professionals, then the mission could be to offer a well-balanced program of instruction, practical experience, and opportunities for overall personality development. Vision is a futuristic statement that the institution would like to achieve over a long period of time, and Mission is the means by which it proposes to move toward the stated Vision.</p>
                    <p>Focus: Where we're going and how we'll arrive.</p>
                </li>
                <li><strong>Program Educational Objectives (PEOs):</strong>
                    <p><strong>Definition:</strong> Program Educational Objectives (PEOs): Program Educational Objectives are broad statements that describe the career and professional accomplishments that the program is preparing graduates to achieve.</p>
                    <p>Focus: Setting goals for our students' professional lives.</p>
                </li>
                <li><strong>Program Outcomes (POs):</strong>
                    <p><strong>Definition:</strong> Program Outcomes (POs): Program Outcomes are statements that describe what students are expected to know and be able to do upon graduating from the program. These relate to the skills, knowledge, attitude and behaviour that students acquire through the program. NBA has defined the Program Outcomes for each discipline.</p>
                    <p>Focus: The skills and abilities our students gain.</p>
                </li>
                <li><strong>Program Specific Outcomes (PSOs):</strong>
                    <p><strong>Definition:</strong> Program Specific Outcomes are statements that describe what students are expected to know and be able to do, specific to the discipline, upon graduating from the program. These relate to the skills, knowledge, attitude and behaviour that students acquire through the program.</p>
                    <p>Focus: The specific skills and abilities related to the program specialization that our students gain.</p>
                </li>
                <li><strong>Course Outcomes (COs):</strong>
                    <p><strong>Definition:</strong> Course Outcomes (COs): Course Outcomes are narrower statements that describe what students are expected to know, and are able to do at the end of each course. These relate to the skills, knowledge and behaviour that students acquire in their progress through the course.</p>
                    <p>Focus: The specific things our students achieve in class.</p>
                </li>
                <li><strong>Assessment</strong>
                    <p><strong>Definition:</strong> Assessment is one or more processes, carried out by the institution, that identify, collect, and prepare data to evaluate the achievement of Program Educational Objectives and Program Outcomes.</p>
                    <p>Focus: Using student data to check if our course teaching is meeting learning goals.</p>
                </li>
            </ul>
            <p class="card-text"><strong>How They Link:</strong> Vision -> Mission -> PEOs -> POs/PSOs -> COs</p>
        </div>
        <div class="card-footer">
            Definitions of Vision, Mission, PEO, PO, and CO, Assessment are based on NBA guidelines. <em style="font-size: small;">Source: <a href="https://www.nbaind.org/Downloads/Documents" target="_blank">NBA Downloads</a></em>
        </div>
    </div>

    <div class="card my-4">
        <div class="card-body">
            <h5 class="card-title text-success">Bloom's Taxonomy: Levels of Learning</h5>
            <p class="card-text">Help students learn at deeper levels:</p>
            <ol class="card-text">
                <li><strong>Remember:</strong> Know the basics.</li>
                <li><strong>Understand:</strong> Explain the ideas.</li>
                <li><strong>Apply:</strong> Use knowledge in new ways.</li>
                <li><strong>Analyze:</strong> Break things down.</li>
                <li><strong>Evaluate:</strong> Make judgments.</li>
                <li><strong>Create:</strong> Make something new.</li>
            </ol>
        </div>
    </div>

    <div class="card my-4">
        <div class="card-body">
            <h5 class="card-title text-success">Mapping COs to POs/PSOs Matters</h5>
            <p class="card-text">See how your course fits into the big plan (program)</p>
            <strong>Creating an Effective Articulation Matrix: (If not defined):</strong>
            <ul class="card-text">
                <li>Begin by listing the clearly defined course outcomes for your subject.</li>
                <li>Review the program's POs and PSOs. These are the broad skills and competencies students should achieve.</li>
                <li>For each CO, determine which POs/PSOs it directly contributes to. (Use a matrix (table) with COs in rows and POs/PSOs in columns.)</li>
                <li>Assign a weightage or correlation value to each mapping. This indicates the strength of the relationship. (Use a scale (e.g., 1-3, where 1 is weak, 2 is medium, and 3 is strong)).</li>
            </ul>
        </div>
    </div>

    <div class="card my-4">
        <div class="card-body bg-light">
            <h5 class="card-title text-success">Creating Great Learning Experiences</h5>
            <div class="alert alert-success text-center">
                "Turn your class into a place where students love to learn."
            </div>
            <div class="mb-3">
                <h5>Step 1: Decode the Articulation Matrix</h5>
                <p>Uncover how your course empowers students to achieve program excellence.</p>
            </div>
            <div class="mb-3">
                <h5>Step 2: Plan Fun and Effective Activities</h5>
                <p>Choose activities that help students learn:</p>
                <ul class="card-text">
                    <li><strong>For Remembering/Understanding:</strong> Quick quizzes, group discussions.</li>
                    <li><strong>For Applying:</strong> Real projects, hands-on work.</li>
                    <li><strong>For Analyzing/Evaluating:</strong> Debates, reviews.</li>
                    <li><strong>For Creating:</strong> Design challenges, presentations.</li>
                </ul>
            </div>
            <div class="mb-3">
                <h5>Step 3: Use Different Teaching Styles</h5>
                <p>Try different ways to teach:</p>
                <ul class="card-text">
                    <li>Talks, demos, group work, online tools, labs.</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="card my-4">
        <div class="card-body bg-light">
            <h5 class="card-title text-success">Making Good Assessment Questions</h5>
            <div class="alert alert-success text-center">
                "Your questions can show students what they know and where they can improve."
            </div>
            <div class="mb-3">
                <h5>Step 1: Link Questions to COs</h5>
                <p>Make sure each question checks a specific learning goal.</p>
            </div>
            <div class="mb-3">
                <h5>Step 2: Use Bloom's Levels</h5>
                <p>Ask questions that challenge different thinking skills:</p>
                <ul class="card-text">
                    <li><strong>Remember:</strong> "Tell me the steps..."</li>
                    <li><strong>Understand:</strong> "Explain why..."</li>
                    <li><strong>Apply:</strong> "Use this to solve..."</li>
                    <li><strong>Analyze:</strong> "Compare these ideas..."</li>
                    <li><strong>Evaluate:</strong> "Which is best and why?"</li>
                    <li><strong>Create:</strong> "Make a new way to..."</li>
                </ul>
            </div>
            <div class="mb-3">
                <h5>Step 3: Add Details to Each Question</h5>
                <p>For each question, add:</p>
                <ul class="card-text">
                    <li><strong>Related CO(s):</strong> Which learning goals it checks.</li>
                    <li><strong>Bloom's Level:</strong> What kind of thinking it tests.</li>
                    <li><strong>Marks Assigned:</strong> How much it's worth.</li>
                </ul>
            </div>
            <div class="mb-3">
                <h5>Step 4: Use Clear Scoring Guides</h5>
                <p>Make grading fair and easy with clear scoring guides.</p>
            </div>
            <div class="mb-3">
                <h5>Why Assessments Matter</h5>
                <p>Good assessments help students learn better and show us how we can improve our teaching. They give students clear feedback and help them know what they need to work on. They also help us see if our teaching is working.</p>
            </div>
        </div>
    </div>

    <div class="card my-4">
        <div class="card-header">
            <strong>CO/PO Attainment: What It Means and Why It Matters?</strong>
        </div>
        <div class="card-body">
            <h5 class="mb-3 text-primary">What It IS:</h5>
            <ul class="list-group list-group-flush">
                <li class="list-group-item"><span class="text-success">✔ Demonstrated Learning:</span> Measures how well students achieve course (CO) and program (PO) objectives.</li>
                <li class="list-group-item"><span class="text-success">✔ Data-Driven:</span> Uses varied assessments for concrete evidence of student achievement.</li>
                <li class="list-group-item"><span class="text-success">✔ An Improvement Tool:</span> Helps refine teaching and curriculum.</li>
                <li class="list-group-item"><span class="text-success">✔ Standards-Aligned:</span> Ensures programs meet accreditation and industry requirements.</li>
                <li class="list-group-item"><span class="text-success">✔ CO-Focused:</span> Evaluates learning within a specific course.</li>
                <li class="list-group-item"><span class="text-success">✔ PO-Focused:</span> Assesses overall program-wide skills and knowledge.</li>
            </ul>

            <h5 class="mt-4 mb-3 text-danger">What It ISN’T:</h5>
            <ul class="list-group list-group-flush">
                <li class="list-group-item"><span class="text-danger">✖ Just Grades:</span> Goes beyond letter grades to deeper learning analysis.</li>
                <li class="list-group-item"><span class="text-danger">✖ A One-Time Process:</span> Requires continuous assessment.</li>
                <li class="list-group-item"><span class="text-danger">✖ Only Exams:</span> Uses multiple assessment methods.</li>
                <li class="list-group-item"><span class="text-danger">✖ A Punishment:</span> Focused on improvement, not blame.</li>
                <li class="list-group-item"><span class="text-danger">✖ Static:</span> Needs regular updates to stay relevant.</li>
                <li class="list-group-item"><span class="text-danger">✖ A Perfection Guarantee:</span> Measures program effectiveness, not individual performance.</li>
            </ul>

            <h5 class="mt-4 text-secondary">Why It Matters:</h5>
            <p>Understanding both what CO/PO attainment <strong>is and isn’t</strong> is crucial for its <strong>effective, data-driven implementation</strong>, ultimately improving <strong>educational quality</strong>.</p>
        </div>
    </div>
</div>

<?php
require_once("facfooter.php");

?>