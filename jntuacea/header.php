<?php require_once __DIR__ . '/bootstrap.php'; ?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>JNTUACEA - Academic Record Book</title>
    <link rel="icon" href="favicon.png" sizes="16x16" type="image/png" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-GLhlTQ8iRABdZLl6O3oVMWSktQOp6b7In1Zl3/Jr59b6EGGoI1aFkw7cmDA6j6gD" crossorigin="anonymous" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
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
    <script>
        window.history.pushState(null, null, window.location.href);
    </script>
<script>
// Attach the stable form token to same-origin AJAX requests.
(function(){const token=<?= json_encode(\College\RequestSecurity::token()) ?>;
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
                    <img src="images/jntuacea.png" alt="JNTUACEA" class="img-fluid responsive-img" />
                </div>
                <div class="col-auto p-0">
                    <span class="text-primary d-block responsive-text p-0">JNTUA College of Engineering Ananthapuramu</span>
                    <span class="text-primary d-block responsive-text2 p-0">(Accredited by NAAC with ’A’ Grade)</span>
                    <span class="text-success d-block responsive-text">Student Academic Record Book</span>
                </div>
            </div>
        </div>
        <?php
require_once __DIR__ . '/core/RequestSecurity.php';
 if (!empty($_SESSION['name'])): ?>
        <div class="text-end">
            <span class="d-block small text-muted">User: <?php echo htmlspecialchars($_SESSION['name']); ?></span>
        </div>
        <?php endif; ?>
    </div>