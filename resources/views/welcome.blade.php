<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/style.css"/>
</head>
<body>
<div class="mainContainer displayTableView">
    <div class="searchBoxContainer">
        <div class="logoMini hidden">
            <img src="image/logo.png" alt="">
        </div>
        <div class="searchBox">
            <div class="center form-group logo">
                <img src="image/logo.png" alt="">
            </div>
            <form onsubmit="submitForm(event)">
                <div class="input-group form-group">
                    <input type="text" id="searchQuery" autocomplete="off"/>
                </div>
                <div class="center toggleDisappear">
                    <button class="button form-group" type="submit">Search</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script type="text/javascript" src="js/main.js"></script>
</body>
</html>