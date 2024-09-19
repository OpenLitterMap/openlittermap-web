<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OpenLitterMap Impact Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f7fa;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .flex {
            display: flex;
        }
        .flex-1 {
            flex: 1;
        }
        .jc {
            justify-content: center;
        }
        .mr1 {
            margin-right: 1em;
        }
        .header, .summary, .categories, .footer {
            background-color: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
        }
        .header h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        .stats {
            display: flex;
            justify-content: space-between;
        }
        .categories {
            display: flex;
            justify-content: space-between;
        }
        .category-card {
            background-color: #edf7f9;
            padding: 15px;
            border-radius: 10px;
            width: 24%;
            text-align: center;
            margin-right: 10px;
        }
        .category-card h3 {
            margin: 0 0 10px;
        }
        .footer {
            text-align: center;
        }
        .footer img {
            width: 50px;
        }
        .map img {
            max-width: 100%;
            border-radius: 10px;
        }
        .flag {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 48px;

            img {
                border-radius: 50%;
                width: 32px;
                height: 32px;
                object-fit: fill;
                margin-right: 10px;
            }
        }
        .relative {
            position: relative;
        }
    </style>
</head>
<body>

<div class="container">

    <div class="header">
        <div class="flex">
            <img
                src="https://openlittermap.com/assets/logo.png"
                style="max-width: 100%; height: auto; width: 10em; align-self: center; flex: 0.3;"
                alt="OpenLitterMap Logo"
            >

            <div style="flex: 0.5;">
                <h1>Impact Report</h1>
                <p>{{ $startDate }} <br> to {{ $endDate }}</p>
            </div>

            <div class="flex flex-1" style="justify-content: space-around; align-items: center;">
                <div class="stats">
                    <div>
                        <p><strong>{{ $newUsers }}</strong> New Users</p>
                        <p><strong>{{ $totalUsers }}</strong> Total Users</p>
                    </div>
                </div>

                <div class="stats">
                    <div>
                        <p><strong>{{ $newPhotos }}</strong> New Photos</p>
                        <p><strong>{{ $totalPhotos }}</strong> Total Photos</p>
                    </div>
                </div>

                <div class="stats">
                    <div>
                        <p><strong>{{ $newTags }}</strong> New Tags</p>
                        <p><strong>{{ $totalTags }}</strong> Total Tags</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="categories">

        <div class="category-card">
            <h3>Top 5 users</h3>

            <div class="flex jc relative">
                <img
                    src="https://openlittermap.com/assets/icons/gold-medal-2.png"
                    alt="Gold Medal"
                    style="width: 2em; margin-right: 10px; position: absolute; top: 0; left: 0;"
                />

                <div class="flag">
                    <img src="https://openlittermap.com/assets/icons/flags/ie.png"/>

                    <p>User1</p>
                </div>
            </div>

            <div class="flex jc relative">
                <img
                    src="https://openlittermap.com/assets/icons/silver-medal-2.png"
                    alt="Silver Medal"
                    style="width: 2em; margin-right: 10px; position: absolute; top: 0; left: 0;"
                />

                <div class="flag">
                    <img src="https://openlittermap.com/assets/icons/flags/nl.png"/>

                    <p>User2</p>
                </div>
            </div>

            <div class="flex jc relative">
                <img
                    src="https://openlittermap.com/assets/icons/bronze-medal-2.png"
                    alt="Bronze Medal"
                    style="width: 2em; margin-right: 10px; position: absolute; top: 0; left: 0;"
                />

                <div class="flag">
                    <img src="https://openlittermap.com/assets/icons/flags/gb.png"/>

                    <p>User3</p>
                </div>
            </div>

            <div class="flex jc relative">
                <div class="flag">
                    <img src="https://openlittermap.com/assets/icons/flags/pt.png"/>

                    <p>User4</p>
                </div>
            </div>

            <div class="flex jc relative">
                <div class="flag">
                    <img src="https://openlittermap.com/assets/icons/flags/de.png"/>

                    <p>User5</p>
                </div>
            </div>
        </div>

        <div class="category-card">
            <h3>Total Litter: 5,000</h3>
            <p>Cigarette Butts: 2,105</p>
            <p>Cans: 1,170</p>
            <p>Bottles: 510</p>
            <p>Coffee Cups: 300</p>
        </div>
        <div class="category-card">
            <h3>Total Brands: 1,217</h3>
            <p>Coca-cola: 500</p>
            <p>Redbull: 200</p>
            <p>Mc Donald's: 100</p>
        </div>
        <div class="category-card">
            <h3>Total Materials: 5,000</h3>
            <p>Plastic: 2,500</p>
            <p>Aluminium: 1,000</p>
            <p>Other: 500</p>
        </div>
    </div>
</div>

</body>
</html>
