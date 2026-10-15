<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "My Learning Journey";
require_once("stheader.php");
?>
    <div class="container">
        <div class="card my-4">
            <div class="card-header bg-primary text-white">
                Understanding My Program
            </div>
            <div class="card-body">
                <p class="card-text">Let's understand some important terms about your program.</p>
                <ul class="card-text">
                    <li><strong>Mission and Vision:</strong>                    
                        <p><strong>What it means:</strong> The vision is what the college wants to achieve in the future, like becoming a top engineering college. The mission is how they plan to do it, like providing good classes and hands-on experience.</p>
                        <p><strong>Think of it as:</strong> Where we're going and how we'll get there.</p>
                    </li>
                    <li><strong>Program Educational Objectives (PEOs):</strong>
                        <p><strong>What it means:</strong> These are goals for what you should be able to do in your career after you graduate.</p>
                        <p><strong>Think of it as:</strong> What kind of successful professional you'll become.</p>
                    </li>
                    <li><strong>Program Outcomes (POs):</strong>
                        <p><strong>What it means:</strong> These are the skills and knowledge you should have when you finish your program. They're like the things you'll be good at.</p>
                        <p><strong>Think of it as:</strong> The skills and abilities you'll gain.</p>
                    </li>
                    <li><strong>Program Specific Outcomes (PSOs):</strong>
                        <p><strong>What it means:</strong> These are special skills you'll learn that are specific to your specialization, like coding for computer science students.</p>
                        <p><strong>Think of it as:</strong> The specific skills for your specialization.</p>
                    </li>
                    <li><strong>Course Outcomes (COs):</strong>
                        <p><strong>What it means:</strong> These are the things you should learn in each class, like being able to solve certain problems or write a specific type of report.</p>
                        <p><strong>Think of it as:</strong> What you'll learn in each class.</p>
                    </li>
                    <li><strong>Assessment</strong>
                        <p><strong>What it means:</strong> This is how your teachers check if you're learning what you're supposed to. It includes tests, assignments, and projects.</p>
                        <p><strong>Think of it as:</strong> Checking if you're learning well.</p>
                    </li>
                </ul>
                <p class="card-text"><strong>How They Connect:</strong> Vision -> Mission -> PEOs -> POs/PSOs -> COs</p>
            </div>
            <div class="card-footer">
                These definitions are based on guidelines that help colleges make sure you get a good education.
            </div>
        </div>

        <div class="card my-4">
            <div class="card-body">
                <h5 class="card-title text-info">Learning Levels: Bloom's Taxonomy</h5>
                <p class="card-text">Learn at deeper levels:</p>
                <ol class="card-text">
                    <li><strong>Remember:</strong> Know the basics.</li>
                    <li><strong>Understand:</strong> Explain the ideas.</li>
                    <li><strong>Apply:</strong> Use what you know.</li>
                    <li><strong>Analyze:</strong> Break things down.</li>
                    <li><strong>Evaluate:</strong> Make choices.</li>
                    <li><strong>Create:</strong> Make something new.</li>
                </ol>
            </div>
        </div>

        <div class="card my-4">
            <div class="card-body">
                <h5 class="card-title text-info">How Your Classes Fit In</h5>
                <p class="card-text">See how your classes help you achieve your program goals.</p>
                <strong>Understanding the Connection:</strong>
                <ul class="card-text">                  
                    <li>Your classes have specific goals (COs).</li>
                    <li>These goals help you gain broader skills (POs/PSOs).</li>
                    <li>This helps you become the professional you want to be (PEOs).</li>
                </ul>
            </div>
        </div>

        <div class="card my-4">
            <div class="card-body bg-light">
                <h5 class="card-title text-info">Making Learning Fun</h5>
                <div class="alert alert-info text-center">
                    "Enjoy learning and make the most of your classes."
                </div>
                <div class="mb-3">
                    <h5>Step 1: Understand Your Class Goals</h5>
                    <p>Know what you're supposed to learn in each class.</p>
                </div>
                <div class="mb-3">
                    <h5>Step 2: Participate in Activities</h5>
                    <p>Get involved in class activities:</p>
                    <ul class="card-text">
                        <li><strong>For Remembering/Understanding:</strong> Join discussions, ask questions.</li>
                        <li><strong>For Applying:</strong> Work on projects, do experiments.</li>
                        <li><strong>For Analyzing/Evaluating:</strong> Share your opinions, review others' work.</li>
                        <li><strong>For Creating:</strong> Make presentations, design solutions.</li>
                    </ul>
                </div>
                <div class="mb-3">
                    <h5>Step 3: Learn in Different Ways</h5>
                    <p>Use different learning methods:</p>
                    <ul class="card-text">
                        <li>Listen to lectures, watch demos, work in groups, use online tools, do lab work.</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="card my-4">
            <div class="card-body bg-light">
                <h5 class="card-title text-info">Understanding Assessments</h5>
                <div class="alert alert-info text-center">
                    "Tests and assignments help you learn and improve."
                </div>
                <div class="mb-3">
                    <h5>Step 1: Know What You're Being Tested On</h5>
                    <p>Understand which learning goals (COs) each test question covers.</p>
                </div>
                <div class="mb-3">
                    <h5>Step 2: Use Different Thinking Skills</h5>
                    <p>Practice different thinking skills in your answers:</p>
                    <ul class="card-text">
                        <li><strong>Remember:</strong> "What are the steps?"</li>
                        <li><strong>Understand:</strong> "Why does this happen?"</li>
                        <li><strong>Apply:</strong> "How can I use this to solve a problem?"</li>
                        <li><strong>Analyze:</strong> "How are these ideas different?"</li>
                        <li><strong>Evaluate:</strong> "Which option is better and why?"</li>
                        <li><strong>Create:</strong> "Can you design a new solution?"</li>
                    </ul>
                </div>
                <div class="mb-3">
                    <h5>Step 3: Understand the Grading</h5>
                    <p>Know how your work will be graded.</p>
                </div>
                <div class="mb-3">
                    <h5>Step 4: Use Feedback to Learn</h5>
                    <p>Pay attention to feedback to know where you can improve.</p>
                </div>
                <div class="mb-3">
                    <h5>Why Assessments Are Important</h5>
                    <p>Assessments help you understand what you've learned and what you need to work on. They also help your teachers make sure you're learning well.</p>
                </div>
            </div>
        </div>
    </div>

<?php
require_once("stfooter.php");
?>