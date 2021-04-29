<html>
<head>
    <title>Multi Currency Pricing</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css"
          integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= URL ?>/assets/css/style.css">
    <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
<!--    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"-->
<!--            integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1"-->
<!--            crossorigin="anonymous"></script>-->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"
            integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM"
            crossorigin="anonymous"></script>
</head>
<body>
<nav class="navbar navbar-dark fixed-top bg-dark flex-md-nowrap p-0 shadow">
    <a class="navbar-brand col-sm-3 col-md-2 mr-0" href="#">Multi Currency Prices</a>
    <form action="<?= URL ?>/products.php" method="GET" class="w-100 mb-0">
        <input class="form-control form-control-dark w-100" type="text" placeholder="Search by Product Title" name="s"
               aria-label="Search" value="<?= ( isset( $_GET['s'] ) ? $_GET['s'] : "" ) ?>">
    </form>
    <ul class="navbar-nav px-3">
        <li class="nav-item text-nowrap">
            <a class="nav-link" href="<?= URL ?>/logout.php">Sign out</a>
        </li>
    </ul>
</nav>
<div class="container-fluid">
    <div class="row">
