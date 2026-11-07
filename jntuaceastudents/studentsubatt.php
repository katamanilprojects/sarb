<?php
require_once __DIR__ . '/bootstrap.php';


if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

require_once("logger.php");
logRequest();
?>
<html lang="en"><head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>JNTUACEA - Academic Record Book</title>
    <link rel="icon" href="favicon.png" sizes="16x16" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-GLhlTQ8iRABdZLl6O3oVMWSktQOp6b7In1Zl3/Jr59b6EGGoI1aFkw7cmDA6j6gD" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style type="text/css">
        html {
            position: relative;
            min-height: 100%;
        }

        body {
            margin-bottom: 60px;
            background-color: #F5F3EE;
        }

        .footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            height: 60px;
            background-color: #f5f5f5;
        }

        .container .text-muted {
            margin: 20px 0;
        }

        .labelapply3 {
            padding-top: 1px !important;
        }

        .responsive-text {
            font-size: 1.3em;
        }

        .responsive-text2 {
            font-size: 1em;
        }

        .responsive-img {
            max-width: 100px;
            max-height: 100px;
            width: auto;
            height: auto;
            aspect-ratio: 311 / 259;
        }

        @media (max-width: 576px) {
            .responsive-text {
                font-size: 0.76em;
            }

            .responsive-text2 {
                font-size: 0.6em;
            }

            .responsive-img {
                max-width: 50px;
                max-height: 50px;
            }
        }

        .doprint {
            display: none;
        }

        @media print {
            .dontprint {
                display: none !important;
            }

            .doprint {
                display: block !important;
            }
        }
    </style>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async="" defer=""></script>
    <script>
        window.history.pushState(null, null, window.location.href);
    </script>
<script>
(function(){const token="6423ceb2080dfddc6f6365f28bf4f9894d5898f966cf2f0c3e5fc1542fc674d7";
const send=XMLHttpRequest.prototype.send;XMLHttpRequest.prototype.send=function(body){if(this.__collegeSameOrigin)this.setRequestHeader('X-CSRF-Token',token);return send.call(this,body)};
const open=XMLHttpRequest.prototype.open;XMLHttpRequest.prototype.open=function(method,url){this.__collegeSameOrigin=new URL(url,location.href).origin===location.origin;return open.apply(this,arguments)};
const original=window.fetch;if(original)window.fetch=function(input,options){options=options||{};const url=new URL(input instanceof Request?input.url:String(input),location.href);if(url.origin===location.origin){const headers=new Headers(options.headers||(input instanceof Request?input.headers:undefined));headers.set('X-CSRF-Token',token);options.headers=headers}return original.call(this,input,options)};
})();
</script>
</head>

<body>
    <div class="container-fluid bg-white p-3">
        <div class="container d-flex justify-content-center">
            <div class="row align-items-center">
                <div class="col-auto">
                    <img src="images/jntuacea.png" alt="JNTUACEA" width="311" height="259" class="img-fluid responsive-img">
                </div>
                <div class="col-auto p-0">
                    <span class="text-primary d-block responsive-text p-0">JNTUA College of Engineering Ananthapuramu</span>
                    <span class="text-primary d-block responsive-text2 p-0">(Accredited by NAAC with ’A’ Grade)</span>
                    <span class="text-success d-block responsive-text">Student Academic Record Book</span>
                </div>
            </div>
        </div>
    </div><div class="container dontprint">
    <div class="row g-1">
                    <div class="col-6 col-sm p-0">
                <a href="studenthome.php" class="w-100 btn btn-outline-primary">Home</a>
            </div>
                    <div class="col-6 col-sm p-0">
                <a href="studentfeedback.php" class="w-100 btn btn-outline-primary">Feedback</a>
            </div>
                    <div class="col-6 col-sm p-0">
                <a href="stchgpwd.php" class="w-100 btn btn-outline-primary">Change Password</a>
            </div>
                    <div class="col-6 col-sm p-0">
                <a href="logout.php" class="w-100 btn btn-outline-danger">Logout</a>
            </div>
            </div>
</div>
<br>
<div class="container">
    <div class="card shadow-lg">
        <div class="card-header">
            <div class="float-start">
                <h6 class="mb-0">
                    B.Tech (CSE) - II Yr - I Sem                                            <span class="badge bg-secondary ms-2">Inactive / Transferred</span>
                                    </h6>
                <small>Academic Year: 2026-2027</small>
            </div>
            <div class="float-end">
                <a href="studenthome.php" class="btn btn-sm btn-outline-primary">Home</a>
            </div>
        </div>
        <div class="card-header">
            23A30502 <br> Python programming        </div>
        <div class="card-body">
                            <div class="alert alert-secondary py-2 mb-3">
                    <i class="bi bi-info-circle me-1"></i><strong>Note:</strong> You are currently marked inactive in this class section (e.g. transferred or shifted mid-semester). Displaying past attendance records accrued during your active enrollment.
                </div>
                                                            <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th colspan="3">Attendance</th>
                            </tr>
                            <tr>
                                <th>Date</th>
                                <th>Hour</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                                                            <tr>
                                    <td>03-07-2026</td>
                                    <td>9:30 AM - 10:30 AM</td>
                                    <td>
                                        <span class="badge bg-danger">Absent</span>                                    </td>
                                </tr>
                                                            <tr>
                                    <td>03-07-2026</td>
                                    <td>10:30 AM - 11:30 AM</td>
                                    <td>
                                        <span class="badge bg-danger">Absent</span>                                    </td>
                                </tr>
                                                            <tr>
                                    <td>03-07-2026</td>
                                    <td>11:45 AM - 12:45 PM</td>
                                    <td>
                                        <span class="badge bg-danger">Absent</span>                                    </td>
                                </tr>
                                                            <tr>
                                    <td>10-07-2026</td>
                                    <td>9:30 AM - 10:30 AM</td>
                                    <td>
                                        <span class="badge bg-success">Present</span>                                    </td>
                                </tr>
                                                            <tr>
                                    <td>10-07-2026</td>
                                    <td>10:30 AM - 11:30 AM</td>
                                    <td>
                                        <span class="badge bg-success">Present</span>                                    </td>
                                </tr>
                                                            <tr>
                                    <td>10-07-2026</td>
                                    <td>11:45 AM - 12:45 PM</td>
                                    <td>
                                        <span class="badge bg-success">Present</span>                                    </td>
                                </tr>
                                                            <tr>
                                    <td>16-07-2026</td>
                                    <td>3:45 PM - 4:45 PM</td>
                                    <td>
                                        <span class="badge bg-danger">Absent</span>                                    </td>
                                </tr>
                                                            <tr>
                                    <td>17-07-2026</td>
                                    <td>9:30 AM - 10:30 AM</td>
                                    <td>
                                        <span class="badge bg-danger">Absent</span>                                    </td>
                                </tr>
                                                            <tr>
                                    <td>17-07-2026</td>
                                    <td>10:30 AM - 11:30 AM</td>
                                    <td>
                                        <span class="badge bg-danger">Absent</span>                                    </td>
                                </tr>
                                                            <tr>
                                    <td>17-07-2026</td>
                                    <td>11:45 AM - 12:45 PM</td>
                                    <td>
                                        <span class="badge bg-danger">Absent</span>                                    </td>
                                </tr>
                                                            <tr>
                                    <td>23-07-2026</td>
                                    <td>10:30 AM - 11:30 AM</td>
                                    <td>
                                        <span class="badge bg-success">Present</span>                                    </td>
                                </tr>
                                                            <tr>
                                    <td>24-07-2026</td>
                                    <td>9:30 AM - 10:30 AM</td>
                                    <td>
                                        <span class="badge bg-danger">Absent</span>                                    </td>
                                </tr>
                                                            <tr>
                                    <td>31-07-2026</td>
                                    <td>9:30 AM - 10:30 AM</td>
                                    <td>
                                        <span class="badge bg-danger">Absent</span>                                    </td>
                                </tr>
                                                    </tbody>
                    </table>
                                <hr>
                        
            <hr>

                            <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th colspan="3">Lecture Diary</th>
                        </tr>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Diary</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>03-07-2026</td><td>9:30 AM - 10:30 AM</td><td> Installing  Anaconda Python Distribution, Installing and Using Jupiter Notebook.</td></tr><tr><td>03-07-2026</td><td>10:30 AM - 11:30 AM</td><td> Installing  Anaconda Python Distribution, Installing and Using Jupiter Notebook.</td></tr><tr><td>03-07-2026</td><td>11:45 AM - 12:45 PM</td><td> Installing Anaconda Python Distribution, Installing and Using Jupiter Notebook.</td></tr><tr><td>10-07-2026</td><td>9:30 AM - 10:30 AM</td><td>1. Write a program to find the largest element among three Numbers. 
2. Write a Program to display all prime numbers with in an interval 
3. Write a program to swap two numbers without using a temporary variable.</td></tr><tr><td>10-07-2026</td><td>10:30 AM - 11:30 AM</td><td>1. Write a program to find the largest element among three Numbers. 
2. Write a Program to display all prime numbers with in an interval 
3. Write a program to swap two numbers without using a temporary variable.</td></tr><tr><td>10-07-2026</td><td>11:45 AM - 12:45 PM</td><td>1. Write a program to find the largest element among three Numbers. 
2. Write a Program to display all prime numbers with in an interval 
3. Write a program to swap two numbers without using a temporary variable.</td></tr><tr><td>16-07-2026</td><td>3:45 PM - 4:45 PM</td><td>Write a program to swap two numbers without using a temporary variable. </td></tr><tr><td>17-07-2026</td><td>9:30 AM - 10:30 AM</td><td>4. Demonstrate the following Operators in Python with suitable examples. 
i) Arithmetic Operators ii) Relational Operators iii) Assignment Operatorship) 
Logical Operators v) Bit wise Operators vi) Ternary Operator vii) Membership 
Operators 
viii) Id</td></tr><tr><td>17-07-2026</td><td>10:30 AM - 11:30 AM</td><td>4. Demonstrate the following Operators in Python with suitable examples. 
i) Arithmetic Operators ii) Relational Operators iii) Assignment Operatorship) 
Logical Operators v) Bit wise Operators vi) Ternary Operator vii) Membership 
Operators 
viii) Id</td></tr><tr><td>17-07-2026</td><td>11:45 AM - 12:45 PM</td><td>4. Demonstrate the following Operators in Python with suitable examples. 
i) Arithmetic Operators ii) Relational Operators iii) Assignment Operatorship) 
Logical Operators v) Bit wise Operators vi) Ternary Operator vii) Membership 
Operators 
viii) Id</td></tr><tr><td>23-07-2026</td><td>10:30 AM - 11:30 AM</td><td>--&gt;Write a program to add and multiply complex numbers 
--&gt;Write a program to print multiplication table of a given number. </td></tr><tr><td>24-07-2026</td><td>9:30 AM - 10:30 AM</td><td>--&gt; Write a program to define a function with multiple return values. 
--&gt; Write a program to define a function using default arguments. </td></tr><tr><td>31-07-2026</td><td>9:30 AM - 10:30 AM</td><td>write a program to find the length of the string without using any library function</td></tr><tr><td>06-08-2026</td><td>3:45 PM - 4:45 PM</td><td>write a program to check if the substring is presenting a given string or not.</td></tr><tr><td>07-08-2026</td><td>9:30 AM - 10:30 AM</td><td> Write a program to perform the given operations on a list: 
i. Addition ii. Insertion  iii. slicing </td></tr><tr><td>07-08-2026</td><td>10:30 AM - 11:30 AM</td><td>. Write a program to perform any 5built-in functions by taking any list.
. Write a program to create tuples (name, age, address, college) for at least two 
members and concatenate the tuples and print the concatenated tuples. 
. Write a program to coun</td></tr><tr><td>07-08-2026</td><td>11:45 AM - 12:45 PM</td><td>. Write a program to perform any 5built-in functions by taking any list.
. Write a program to create tuples (name, age, address, college) for at least two 
members and concatenate the tuples and print the concatenated tuples. 
. Write a program to coun</td></tr><tr><td>14-08-2026</td><td>9:30 AM - 10:30 AM</td><td>Write a program to check if a given key exists in a dictionary or not. 
 Write a program to adda new key-value pair to an existing dictionary. 
Write a program to sum all the items in a given dictionary. </td></tr><tr><td>14-08-2026</td><td>10:30 AM - 11:30 AM</td><td>Write a program to check if a given key exists in a dictionary or not. 
 Write a program to adda new key-value pair to an existing dictionary. 
Write a program to sum all the items in a given dictionary. </td></tr><tr><td>14-08-2026</td><td>11:45 AM - 12:45 PM</td><td>Write a program to sort words in a file and put them in another file. The output file 
should have only lower-case words, so any upper-case words from source must be 
lowered. </td></tr><tr><td>28-08-2026</td><td>9:30 AM - 10:30 AM</td><td>-- Python program to print each line of a file in reverse order. 
-- Python program to compute the number of characters, words and lines in a file. 
-- Write a program to create, display, append, insert and reverse the order of the items in the array. 
</td></tr><tr><td>28-08-2026</td><td>10:30 AM - 11:30 AM</td><td>-- Python program to print each line of a file in reverse order. 
-- Python program to compute the number of characters, words and lines in a file. 
-- Write a program to create, display, append, insert and reverse the order of the items in the array. 
</td></tr><tr><td>28-08-2026</td><td>11:45 AM - 12:45 PM</td><td>-- Python program to print each line of a file in reverse order. 
-- Python program to compute the number of characters, words and lines in a file. 
-- Write a program to create, display, append, insert and reverse the order of the items in the array. 
</td></tr><tr><td>11-09-2026</td><td>9:30 AM - 10:30 AM</td><td>- python program to create a class that represent a shape. Include methods to calculate its area and perimeter. implement sub classes for different shapes like circle, triangle and square.  
-python program to check whether a JSON string contains complex</td></tr><tr><td>11-09-2026</td><td>10:30 AM - 11:30 AM</td><td>- python program to create a class that represent a shape. Include methods to calculate its area and perimeter. implement sub classes for different shapes like circle, triangle and square.  
-python program to check whether a JSON string contains complex</td></tr><tr><td>18-09-2026</td><td>9:30 AM - 10:30 AM</td><td>--Python program to demonstrate use of dim, shape, size, type.
-- Python program to demonstrate basic slicing, integer and Boolean indexing.</td></tr><tr><td>18-09-2026</td><td>10:30 AM - 11:30 AM</td><td>--Python program to demonstrate use of dim, shape, size, type.
-- Python program to demonstrate basic slicing, integer and Boolean indexing.</td></tr><tr><td>18-09-2026</td><td>11:45 AM - 12:45 PM</td><td>--Python program to demonstrate use of dim, shape, size, type.
-- Python program to demonstrate basic slicing, integer and Boolean indexing.</td></tr><tr><td>25-09-2026</td><td>9:30 AM - 10:30 AM</td><td>.Python program to find min, max, sum, cumulative sum of arrays.
. Create a dictionary with at least five keys and each key represent value as a list where this list contains at least ten values and convert this dictionary as a pandas data frame and expl</td></tr><tr><td>25-09-2026</td><td>10:30 AM - 11:30 AM</td><td>.Python program to find min, max, sum, cumulative sum of arrays.
. Create a dictionary with at least five keys and each key represent value as a list where this list contains at least ten values and convert this dictionary as a pandas data frame and expl</td></tr><tr><td>25-09-2026</td><td>11:45 AM - 12:45 PM</td><td>.Python program to find min, max, sum, cumulative sum of arrays.
. Create a dictionary with at least five keys and each key represent value as a list where this list contains at least ten values and convert this dictionary as a pandas data frame and expl</td></tr><tr><td>09-10-2026</td><td>9:30 AM - 10:30 AM</td><td>select any two columns from the above data frame and observe the change in one attribute with respect to other attribute with scatter plot operations in matplot lib.</td></tr><tr><td>09-10-2026</td><td>10:30 AM - 11:30 AM</td><td>select any two columns from the above data frame and observe the change in one attribute with respect to other attribute with scatter plot operations in matplot lib.</td></tr><tr><td>09-10-2026</td><td>11:45 AM - 12:45 PM</td><td>select any two columns from the above data frame and observe the change in one attribute with respect to other attribute with scatter plot operations in matplot lib.</td></tr>                    </tbody>
                </table>
            


                            <table class="table table-bordered"><thead><tr><th colspan="2">Continuous Internal Assessment</th></tr></thead><tbody>
                                <tr><th>Final CIA</th><td>Pending — No finalized assessment records.</td></tr>
                </tbody></table>
            
                    </div>
    </div>
</div>

<br>
<div class="footer">
    <div class="container dontprint">
        <div class="row">
            <div class="col-sm-6"><br> <span class="text-success">© JNTUACEA -
                    All&nbsp;rights&nbsp;reserved.
                    <!--| <a href="terms.html">Terms&nbsp;&&nbsp;Conditions</a>-->
                </span> </div>
            <div class="col-sm-6"><br></div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js" integrity="sha384-w76AqPfDkMBDXo30jS1Sgez6pr3x5MlQ1ZAGC+nuZB+EYdgRZgiwxhTBTkF7CXvN" crossorigin="anonymous">
</script>


</body></html>