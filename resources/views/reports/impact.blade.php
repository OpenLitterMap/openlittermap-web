<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OpenLitterMap Impact Report</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f7fa;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 1080px;
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
            justify-content: center;
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
        .medal {
            display: flex;
            align-items: center;
            margin-right: 1em;
        }
        .rank {
            display: flex;
            flex-direction: row;
            width: 96px;
            gap: 0;
            text-align: center;
            align-items: center;
        }
        .details {
            display: flex;
            align-items: center;
            max-width: 200px;
            text-align: left;
        }
        .social-container {
            display: flex;
            flex: 1;
            flex-direction: row;
            gap: 0.3rem;
            justify-content: flex-end;
            color: #3273dc;
            align-items: center;

            a {
                width: 20px;
                text-decoration: none;
            }
            a:hover {
                transform: scale(1.1);
                color: #3273dc;
            }

            i {
                color: #3273dc;
            }
        }
        .top-user-row {
            display: flex;
            position: relative;
            height: 50px;
        }
        .top-litter-row {
            height: 50px;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
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
                        <p><strong>{{ number_format($newUsers) }}</strong> New Users</p>
                        <p><strong>{{ number_format($totalUsers) }}</strong> Total Users</p>
                    </div>
                </div>

                <div class="stats">
                    <div>
                        <p><strong>{{ number_format($newPhotos) }}</strong> New Photos</p>
                        <p><strong>{{ number_format($totalPhotos) }}</strong> Total Photos</p>
                    </div>
                </div>

                <div class="stats">
                    <div>
                        <p><strong>{{ number_format($newTags) }}</strong> New Tags</p>
                        <p><strong>{{ number_format($totalTags) }}</strong> Total Tags</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="categories">

        <div class="category-card" style="flex: 1.5;">
            <h3>Top 10 Users</h3>

            @if (count($topUsers) > 0)
            @foreach ($topUsers as $index => $topUser)
                <div class="top-user-row">

                    <div class="medal">
                        @if ($index <= 2)
                            <img
                                src="{{ $medals[$index]['src'] }}"
                                alt="{{ $medals[$index]['alt'] }}"
                                style="display: flex; align-items: center; margin-left: 1em; width: 20px;"
                            />
                        @else
                            <div style="width: 20px; margin-left: 1em;"></div>
                        @endif
                    </div>

                    <div class="rank">
                        <span style="flex: 1;">{{ $topUser['ordinal'] }}</span>

                        <div class="flag">
                            @if ($topUser['global_flag'])
                                <img
                                    src="https://openlittermap.com/assets/icons/flags/{{ strtolower($topUser['global_flag']) }}.png"
                                    alt="{{ $topUser['global_flag'] }} Flag"
                                />
                            @endif
                        </div>
                    </div>

                    <div class="details">
                        @if($topUser['name'] || $topUser['username'])
                            <span>{{ $topUser['name'] }} {{ $topUser['username'] }}</span>
                        @else
                            <span>Anonymous</span>
                        @endif
                    </div>

                    <div>
                        {{ $topUser['xp'] }} xp
                    </div><!DOCTYPE html>
                    <html lang="en">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <title>OpenLitterMap Impact Report</title>
                        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css">
                        <style>
                            body {
                                font-family: Arial, sans-serif;
                                background-color: #f5f7fa;
                                color: #333;
                                margin: 0;
                                padding: 0;
                            }
                            .container {
                                width: 1080px;
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
                                justify-content: center;
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
                            .medal {
                                display: flex;
                                align-items: center;
                                margin-right: 1em;
                            }
                            .rank {
                                display: flex;
                                flex-direction: row;
                                width: 96px;
                                gap: 0;
                                text-align: center;
                                align-items: center;
                            }
                            .details {
                                display: flex;
                                align-items: center;
                                max-width: 200px;
                                text-align: left;
                            }
                            .social-container {
                                display: flex;
                                flex: 1;
                                flex-direction: row;
                                gap: 0.3rem;
                                justify-content: flex-end;
                                color: #3273dc;
                                align-items: center;

                                a {
                                    width: 20px;
                                    text-decoration: none;
                                }
                                a:hover {
                                    transform: scale(1.1);
                                    color: #3273dc;
                                }

                                i {
                                    color: #3273dc;
                                }
                            }
                            .top-user-row {
                                display: flex;
                                position: relative;
                                height: 50px;
                            }
                            .top-litter-row {
                                height: 50px;
                                margin: 0;
                                display: flex;
                                justify-content: center;
                                align-items: center;
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
                                            <p><strong>{{ number_format($newUsers) }}</strong> New Users</p>
                                            <p><strong>{{ number_format($totalUsers) }}</strong> Total Users</p>
                                        </div>
                                    </div>

                                    <div class="stats">
                                        <div>
                                            <p><strong>{{ number_format($newPhotos) }}</strong> New Photos</p>
                                            <p><strong>{{ number_format($totalPhotos) }}</strong> Total Photos</p>
                                        </div>
                                    </div>

                                    <div class="stats">
                                        <div>
                                            <p><strong>{{ number_format($newTags) }}</strong> New Tags</p>
                                            <p><strong>{{ number_format($totalTags) }}</strong> Total Tags</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="categories">

                            <div class="category-card" style="flex: 1.5;">
                                <h3>Top 10 Users</h3>

                                @if (count($topUsers) > 0)
                                    @foreach ($topUsers as $index => $topUser)
                                        <div class="top-user-row" title="{{ $topUser['xp'] }} XP">

                                            <div class="medal">
                                                @if ($index <= 2)
                                                    <img
                                                            src="{{ $medals[$index]['src'] }}"
                                                            alt="{{ $medals[$index]['alt'] }}"
                                                            style="display: flex; align-items: center; margin-left: 1em; width: 20px;"
                                                    />
                                                @else
                                                    <div style="width: 20px; margin-left: 1em;"></div>
                                                @endif
                                            </div>

                                            <div class="rank">
                                                <span style="flex: 1;">{{ $topUser['ordinal'] }}</span>

                                                <div class="flag">
                                                    @if ($topUser['global_flag'])
                                                        <img
                                                                src="https://openlittermap.com/assets/icons/flags/{{ strtolower($topUser['global_flag']) }}.png"
                                                                alt="{{ $topUser['global_flag'] }} Flag"
                                                        />
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="details">
                                                @if($topUser['name'] || $topUser['username'])
                                                    <span>{{ $topUser['name'] }} {{ $topUser['username'] }}</span>
                                                @else
                                                    <span>Anonymous</span>
                                                @endif
                                            </div>

                                            @if ($topUser['social'])
                                                <div class="social-container">
                                                    @foreach (array_slice($topUser['social'], 0, 3) as $social => $url)
                                                        <a href="{{ $url }}" target="_blank">
                                                            <i class="fa {{ $social === 'personal' ? 'fa-link' : 'fa-' . $social }}"></i>
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                @endif
                            </div>

                            <div class="category-card">
                                <h3>Top 10 Tags</h3>

                                @if (count($topTags) > 0)
                                    @foreach ($topTags as $tag => $quantity)
                                        <p class="top-litter-row">{{ $tag }}: {{ $quantity }}</p>
                                    @endforeach
                                @endif
                            </div>

                            <div class="category-card">
                                <h3>Top 10 Brands</h3>

                                @if (count($topBrands) > 0)
                                    @foreach ($topBrands as $brand => $quantity)
                                        <p class="top-litter-row">{{ $brand }}: {{ $quantity }}</p>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>

                    </body>
                    </html>


                @if ($topUser['social'])
                        <div class="social-container">
                            @foreach (array_slice($topUser['social'], 0, 3) as $social => $url)
                                <a href="{{ $url }}" target="_blank">
                                    <i class="fa {{ $social === 'personal' ? 'fa-link' : 'fa-' . $social }}"></i>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
            @endif
        </div>

        <div class="category-card">
            <h3>Top 10 Tags</h3>

            @if (count($topTags) > 0)
                @foreach ($topTags as $tag => $quantity)
                    <p class="top-litter-row">{{ $tag }}: {{ $quantity }}</p>
                @endforeach
            @endif
        </div>

        <div class="category-card">
            <h3>Top 10 Brands</h3>

            @if (count($topBrands) > 0)
                @foreach ($topBrands as $brand => $quantity)
                    <p class="top-litter-row">{{ $brand }}: {{ $quantity }}</p>
                @endforeach
            @endif
        </div>
    </div>
</div>

</body>
</html>
