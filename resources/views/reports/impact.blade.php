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
        .impact-logo {
            max-width: 100%;
            height: auto;
            width: 10em;
            align-self: center;
            flex: 0.3;
            margin-right: 2em;
        }
        .stats {
            display: flex;
            justify-content: space-between;
        }
        .stats p.total {
            font-size: 13px;
            color: #999;
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
        }
        .flag img {
            border-radius: 50%;
            width: 32px;
            height: 32px;
            object-fit: fill;
            margin-right: 10px;
        }
        .relative {
            position: relative;
        }
        .medal {
            display: flex;
            align-items: center;
            width: 36px;
            flex-shrink: 0;
        }
        .rank {
            display: flex;
            flex-direction: row;
            width: 80px;
            flex-shrink: 0;
            align-items: center;
        }
        .details {
            display: flex;
            align-items: center;
            flex: 1;
            min-width: 0;
            text-align: left;
        }
        .details span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .social-container {
            display: flex;
            flex-direction: row;
            gap: 0.4rem;
            color: #3273dc;
            align-items: center;
            margin-left: auto;
            flex-shrink: 0;
        }
        .social-container a {
            width: 20px;
            text-decoration: none;
        }
        .social-container a:hover {
            transform: scale(1.1);
            color: #3273dc;
        }
        .social-container i {
            color: #3273dc;
        }
        .top-user-row {
            display: flex;
            flex-direction: column;
            padding: 6px 0;
            border-bottom: 1px solid #e8eff3;
        }
        .top-user-row:last-child {
            border-bottom: none;
        }
        .user-main {
            display: flex;
            align-items: center;
            height: 36px;
        }
        .user-stats {
            display: flex;
            gap: 1rem;
            margin-left: 116px;
            font-size: 11px;
            color: #888;
            padding-top: 2px;
        }
        .user-stats span {
            white-space: nowrap;
        }
        .top-litter-row {
            height: 32px;
            margin: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 12px;
            font-size: 14px;
            border-bottom: 1px solid #e8eff3;
        }
        .top-litter-row:last-of-type {
            border-bottom: none;
        }
        .top-litter-row .label {
            color: #333;
        }
        .top-litter-row .qty {
            font-weight: bold;
            color: #3273dc;
        }
        .empty-state {
            color: #999;
            padding: 2em 0;
            font-style: italic;
        }
        .report-footer {
            text-align: center;
            font-size: 11px;
            color: #999;
            padding: 10px 0 4px;
        }
    </style>
</head>
<body>

<div class="container">

    <div class="header">
        <div class="flex">
            <img
                src="https://openlittermap.com/assets/logo.png"
                class="impact-logo"
                alt="OpenLitterMap Logo"
            >

            <div style="flex: 0.5;">
                <h1>{{ ucfirst($period) }} Impact Report</h1>

                @if ($period === 'weekly')
                    <p>{{ $startDate }} <br> to {{ $endDate }}</p>
                @elseif ($period === 'monthly')
                    <p>{{ $startDate }}</p>
                @elseif ($period === 'annual')
                    <p>{{ $startDate }} Annual Report</p>
                @endif
            </div>

            <div class="flex flex-1" style="justify-content: space-around; align-items: center;">
                <div class="stats">
                    <div>
                        <p><strong>{{ number_format($newUsers) }}</strong> New Users</p>
                        <p class="total">{{ number_format($totalUsers) }} Total Users</p>
                    </div>
                </div>

                <div class="stats">
                    <div>
                        <p><strong>{{ number_format($newPhotos) }}</strong> New Photos</p>
                        <p class="total">{{ number_format($totalPhotos) }} Total Photos</p>
                    </div>
                </div>

                <div class="stats">
                    <div>
                        <p><strong>{{ number_format($newTags) }}</strong> New Tags</p>
                        <p class="total">{{ number_format($totalTags) }} Total Tags</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="categories">

        <div class="category-card" style="flex: 1.5;">
            <h3>Top 10 Users</h3>

            @forelse ($topUsers as $index => $topUser)
                <div class="top-user-row" title="{{ $topUser['xp'] }} XP">
                    <div class="user-main">
                        <div class="medal">
                            @if ($index <= 2)
                                <img
                                    src="{{ $medals[$index]['src'] }}"
                                    alt="{{ $medals[$index]['alt'] }}"
                                    style="width: 20px;"
                                />
                            @else
                                <div style="width: 20px;"></div>
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

                        @if (!empty($topUser['social']) && is_array($topUser['social']))
                            <div class="social-container">
                                @foreach (array_slice($topUser['social'], 0, 3) as $social => $url)
                                    <a href="{{ $url }}" target="_blank" rel="noopener">
                                        <i class="fa {{ $social === 'personal' ? 'fa-link' : 'fa-' . $social }}"></i>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="user-stats">
                        <span><strong>{{ $topUser['xp'] }} XP</strong></span>
                        <span>{{ $topUser['uploads'] }} photos</span>
                        <span>{{ $topUser['tags'] }} tags</span>
                    </div>
                </div>
            @empty
                <p class="empty-state">No user activity recorded this period.</p>
            @endforelse
        </div>

        <div class="category-card">
            <h3>Top 10 Tags</h3>

            @forelse ($topTags as $tag => $quantity)
                <div class="top-litter-row">
                    <span class="label">{{ $tag }}</span>
                    <span class="qty">{{ number_format($quantity) }}</span>
                </div>
            @empty
                <p class="empty-state">No litter tagged this period.</p>
            @endforelse
        </div>

        <div class="category-card">
            <h3>Top 10 Brands</h3>

            @forelse ($topBrands as $brand => $quantity)
                <div class="top-litter-row">
                    <span class="label">{{ $brand }}</span>
                    <span class="qty">{{ number_format($quantity) }}</span>
                </div>
            @empty
                <p class="empty-state">No branded litter recorded this period.</p>
            @endforelse
        </div>
    </div>

    <div class="report-footer">
        openlittermap.com/impact · Generated {{ now()->format('j M Y') }}
    </div>
</div>

</body>
</html>
