<!DOCTYPE html>
<html>
<head>
    <title>Welcome to OpenLitterMap</title>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <style type="text/css">
        /* CLIENT-SPECIFIC STYLES */
        body, table, td, a{-webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;} /* Prevent WebKit and Windows mobile changing default text sizes */
        table, td{mso-table-lspace: 0pt; mso-table-rspace: 0pt;} /* Remove spacing between tables in Outlook 2007 and up */
        img{-ms-interpolation-mode: bicubic;} /* Allow smoother rendering of resized image in Internet Explorer */

        /* RESET STYLES */
        img{border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none;}
        table{border-collapse: collapse !important;}
        body{height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important;}

        /* iOS BLUE LINKS */
        a[x-apple-data-detectors] {
            color: inherit !important;
            text-decoration: none !important;
            font-size: inherit !important;
            font-family: inherit !important;
            font-weight: inherit !important;
            line-height: inherit !important;
        }

        /* MOBILE STYLES */
        @media screen and (max-width: 525px) {

            /* ALLOWS FOR FLUID TABLES */
            .wrapper {
                width: 100% !important;
                max-width: 100% !important;
            }

            /* ADJUSTS LAYOUT OF LOGO IMAGE */
            .logo img {
                margin: 0 auto !important;
            }

            /* USE THESE CLASSES TO HIDE CONTENT ON MOBILE */
            .mobile-hide {
                display: none !important;
            }

            .img-max {
                max-width: 100% !important;
                width: 100% !important;
                height: auto !important;
            }

            /* FULL-WIDTH TABLES */
            .responsive-table {
                width: 100% !important;
            }

            /* UTILITY CLASSES FOR ADJUSTING PADDING ON MOBILE */
            .padding {
                padding: 10px 5% 15px 5% !important;
            }

            .padding-meta {
                padding: 30px 5% 0px 5% !important;
                text-align: center;
            }

            .padding-copy {
                padding: 10px 5% 10px 5% !important;
                text-align: center;
            }

            .no-padding {
                padding: 0 !important;
            }

            .section-padding {
                padding: 40px 15px 50px 15px !important;
            }

            /* ADJUST BUTTONS ON MOBILE */
            .mobile-button-container {
                margin: 0 auto;
                width: 100% !important;
            }

            .mobile-button {
                padding: 15px !important;
                border: 0 !important;
                font-size: 16px !important;
                display: block !important;
            }

        }

        /* ANDROID CENTER FIX */
        div[style*="margin: 16px 0;"] { margin: 0 !important; }
    </style>
    <!--[if gte mso 12]>
    <style type="text/css">
        .mso-right {
            padding-left: 20px;
        }
    </style>
    <![endif]-->
</head>
<body style="margin: 0 !important; padding: 0 !important;">

<!-- HIDDEN PREHEADER TEXT -->
<div style="display: none; font-size: 1px; color: #fefefe; line-height: 1px; font-family: Helvetica, Arial, sans-serif; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
    Important Information to Save Our Planet - OpenLitterMap
</div>

<!-- HEADER -->
<table border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding: 0px 15px 70px 15px;" class="section-padding">
            <table align="center" border="0" cellspacing="0" cellpadding="0" width="500">
                <tr>
                    <td align="center" valign="top" width="500">
                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 500px;" class="responsive-table">
                            <tr>
                                <td>
                                    <!-- HERO IMAGE -->
                                    <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                        <tr>
                                            <td align="center" class="padding">
                                                <a href="http://www.openlittermap.com" target="_blank">
                                                    <img src="https://openlittermap.com/assets/OLM_Logo.jpg"
                                                         width="500"
                                                         border="0"
                                                         alt="Global Map Showing OpenLitterMap Data"
                                                         style="display: block; padding: 0; color: #266e9c; text-decoration: none; font-family: Helvetica, arial, sans-serif; font-size: 16px;"
                                                         class="img-max">
                                                </a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <!-- COPY -->
                                                <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                                    <tr>
                                                        <td align="center" style="font-size: 36px; font-family: Helvetica, Arial, sans-serif; color: #266e9c;" class="padding-copy"><strong>We need your help</strong></td>
                                                    </tr>
                                                    <tr>
                                                        <td align="center" style="font-size: 18px; font-family: Helvetica, Arial, sans-serif; color: #266e9c; padding-top: 15px;" class="padding-copy">Verify your email below to get started</td>
                                                    </tr>
                                                </table>

                                                <div align="center" style="border-radius: 3px;margin-top: 50px;background-color: #2ecc71">
                                                    <a href="{{ route('confirm-email-token', $user->token) }}" target="_blank" style="font-size: 16px; font-family: Helvetica, Arial, sans-serif; color: #ffffff; text-decoration: none; color: #000000; text-decoration: none; border-radius: 3px; padding: 15px 25px; display: inline-block;" class="mobile-button">
                                                        Verify Your Email Address and Log In &rarr;
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td bgcolor="#D8F1FF" align="center" style="padding: 70px 15px 25px 15px;" class="section-padding">
            <table border="0" cellpadding="0" cellspacing="0" width="500" style="padding:0 0 20px 0;" class="responsive-table">
                <tr>
                    <td align="center" height="100%" valign="top" width="100%" style="padding-bottom: 35px;">
                        <table align="center" border="0" cellspacing="0" cellpadding="0" width="500">
                            <tr>
                                <td align="center" valign="top" width="500">
                                    <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                        <tr>
                                            <td align="center" style="font-size: 24px; font-family: Helvetica, Arial, sans-serif; color: #333; padding-bottom: 25px;" class="padding-copy">
                                                <p style="padding-bottom: 0px !important; margin-bottom: 0px !important;">{{'@' . $user->username}}</p>
                                                <br/>
                                                <span>Ready to make a difference?</span>
                                            </td>
                                        </tr>
                                    </table>
                                    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:500px;margin-top: 16px;">
                                        <tr>
                                            <td align="center" valign="top" style="font-size:0;">
                                                <table align="center" border="0" cellspacing="0" cellpadding="0" width="500">
                                                    <tr>
                                                        <td align="left" valign="top" width="150">
                                                            <div style="display:inline-block; margin: 0 -2px; max-width:150px; vertical-align:top; width:100%;">

                                                                <table align="left" border="0" cellpadding="0" cellspacing="0" width="150">
                                                                    <tr>
                                                                        <td valign="top">
                                                                            <a href="http://www.openlittermap.com" target="_blank">
                                                                                <img src="https://openlittermap.com/assets/icons/home/camera.png"
                                                                                     alt="Take a picture"
                                                                                     width="150"
                                                                                     border="0"
                                                                                     style="display: block; font-family: Arial; color: #266e9c; font-size: 14px;"
                                                                                />
                                                                            </a>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </div>
                                                        </td>

                                                        <td align="left" valign="top" width="350">
                                                            <div style="display:inline-block; margin: 0 -2px; max-width:350px; vertical-align:top; width:100%;" class="wrapper">

                                                                <table align="left" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 325px; float: right;" class="wrapper">
                                                                    <tr>

                                                                        <td style="padding: 40px 0 0 0;" class="no-padding">
                                                                            <table border="0" cellspacing="0" cellpadding="0" width="100%">
                                                                                <tr>
                                                                                    <td align="left" style="text-align: left; padding: 0 0 5px 0; font-size: 22px; font-family: Helvetica, Arial, sans-serif; font-weight: normal; color: #333333;" class="padding-copy">1. Take a Picture</td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td align="left" style="text-align: left; padding: 10px 0 15px 0; font-size: 16px; line-height: 24px; font-family: Helvetica, Arial, sans-serif; color: #666666;" class="padding-copy">It's easy. Just take a picture of some litter. Make sure your location services are turned on*.</td>
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                    <td align="center" style="padding: 10px 0 15px 0; font-size: 16px; line-height: 24px; font-family: Helvetica, Arial, sans-serif; color: #666666;" class="padding-copy">
                                                                        <div style="text-align: left">
                                                                            <strong>
                                                                                * iPhone. Open Settings -> Privacy -> Location. Turn "Camera On While Using".
                                                                                <br>
                                                                                <br>
                                                                                * Android. Open the Camera. Go to camera settings => Activate Geotagging. Pull down top-menu and Activate GPS
                                                                            </strong>
                                                                            <p>Your photos are now geotagged!</p>
                                                                        </div>
                                                                    </td>
                                                                </table>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td align="center" height="100%" valign="top" width="100%" style="padding-bottom: 35px;">
                        <table align="center" border="0" cellspacing="0" cellpadding="0" width="500">
                            <tr>
                                <td align="center" valign="top" width="500">
                                    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:500px;">
                                        <tr>
                                            <td align="center" valign="top" style="font-size:0;" dir="rtl">
                                                <table align="center" border="0" cellspacing="0" cellpadding="0" width="500">
                                                    <tr>
                                                        <td align="left" valign="top" width="150">
                                                            <div style="display:inline-block; margin: 0 -2px; max-width:150px; vertical-align:top; width:100%;" dir="ltr">
                                                                <table align="left" border="0" cellpadding="0" cellspacing="0" width="150">
                                                                    <tr>
                                                                        <td valign="top"><a href="http://www.openlittermap.com" target="_blank">
                                                                                <img src="https://openlittermap.com/assets/icons/home/phone.png"
                                                                                     alt="smart phone with an icon indicating uploading to the cloud"
                                                                                     width="150"
                                                                                     border="0"
                                                                                     style="display: block; font-family: Arial; color: #666666; font-size: 14px;"
                                                                                />
                                                                            </a>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </div>
                                                        </td>
                                                        <td align="left" valign="top" width="350">
                                                            <div style="display:inline-block; margin: 0 -2px; max-width:350px; vertical-align:top; width:100%;" dir="ltr">
                                                                <table align="left" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 325px;">
                                                                    <tr>
                                                                        <td style="padding: 40px 0 0 0;" class="no-padding">
                                                                            <table border="0" cellspacing="0" cellpadding="0" width="100%">
                                                                                <tr>
                                                                                    <td align="left" style="text-align: left; padding: 0 0 5px 0; font-size: 22px; font-family: Helvetica, Arial, sans-serif; font-weight: normal; color: #333333;" class="padding-copy">2. Tag the litter</td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td align="left" style="text-align: left; padding: 10px 0 15px 0; font-size: 16px; line-height: 24px; font-family: Helvetica, Arial, sans-serif; color: #666666;" class="padding-copy">Just tag what litter you see in the photo. You can tag if the litter has been picked up or if it's still there!</td>
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td align="center" height="100%" valign="top" width="100%" style="padding-bottom: 25px;">
                        <table align="center" border="0" cellspacing="0" cellpadding="0" width="500">
                            <tr>
                                <td align="center" valign="top" width="500">
                                    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:500;">
                                        <tr>
                                            <td align="center" valign="top" style="font-size:0;"> gs
                                                <table align="center" border="0" cellspacing="0" cellpadding="0" width="500">
                                                    <tr>
                                                        <td align="left" valign="top" width="150">
                                                            <div style="display:inline-block; margin: 0 -2px; max-width:150px; vertical-align:top; width:100%;">

                                                                <table align="left" border="0" cellpadding="0" cellspacing="0" width="150">
                                                                    <tr>
                                                                        <td valign="top">
                                                                            <a href="http://openlittermap.com" target="_blank">
                                                                                <img src="https://openlittermap.com/assets/confirm/phone-upload.png"
                                                                                     alt="map style pin indicating location"
                                                                                     width="150"
                                                                                     border="0"
                                                                                     style="display: block; font-family: Arial; color: #666666; font-size: 14px;">
                                                                            </a>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </div>
                                                        </td>
                                                        <td align="left" valign="top" width="350">
                                                            <div style="display:inline-block; margin: 0 -2px; max-width:350px; vertical-align:top; width:100%;" class="wrapper">
                                                                <table align="left" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 325px; float: right;" class="wrapper">
                                                                    <tr>
                                                                        <td style="padding: 40px 0 0 0;" class="no-padding">
                                                                            <table border="0" cellspacing="0" cellpadding="0" width="100%">
                                                                                <tr>
                                                                                    <td align="left" style="text-align: left; padding: 0 0 5px 0; font-size: 22px; font-family: Helvetica, Arial, sans-serif; font-weight: normal; color: #333333;" class="padding-copy">3. Upload Your Tagged Images</td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td align="left" style="text-align: left; padding: 10px 0 15px 0; font-size: 16px; line-height: 24px; font-family: Helvetica, Arial, sans-serif; color: #666666;" class="padding-copy">OpenLitterMap automatically maps your photos so you don't need to remember when or where you took the picture. Keep OpenLitterMap.com/global open to see a live feed of people uploading new photos!</td>
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td bgcolor="#ffffff" align="center" style="padding: 25px 15px 70px 15px;" class="section-padding">
            <table border="0" cellpadding="0" cellspacing="0" width="500" class="responsive-table">

                {{-- Slack and Community Info --}}
                <tr>
                    <td align="center">
                        <p style="margin-top: 3em; font-family: Helvetica, Arial, sans-serif;">Want to become more active in the OpenLitterMap community?</p>
                        <br>
                        <p style=" font-family: Helvetica, Arial, sans-serif;">We use the Slack app to chat. We would love if you could join us to share ideas about the future direction of OpenLitterMap!</p>
                        <br>
                        <p style=" font-family: Helvetica, Arial, sans-serif;">Nearly every week, we hold a community zoom call for an hour where we discuss a different aspect of OpenLitterMap from presentations, to app design and functionality, to grant applications and just getting to know each other! These typically take place at 6pm Irish time. You will find more information on slack.</p>
                        <br>
                        <a
                           href="https://join.slack.com/t/openlittermap/shared_invite/zt-fdctasud-mu~OBQKReRdC9Ai9KgGROw" target="_blank"
                           style="
                                    font-size: 16px;
                                    font-family: Helvetica, Arial, sans-serif;
                                    color: #ffffff;
                                    text-decoration: none;
                                    border-radius: 3px;
                                    padding: 15px 25px;
                                    display: block;
                                    background-color: #4A154B;
                                "
                           class="mobile-button">
                            Join us on Slack
                        </a>

                        <p style="font-family: Helvetica, Arial, sans-serif;">OR</p>

                        <a
                            href="https://openlittermap.com/community" target="_blank"
                            style="
                                    font-size: 16px;
                                    font-family: Helvetica, Arial, sans-serif;
                                    color: #ffffff;
                                    text-decoration: none;
                                    border-radius: 3px;
                                    padding: 15px 25px;
                                    display: block;
                                    background-color: #094C54;
                                "
                            class="mobile-button">
                            Explore the Community
                        </a>

                        <br>
                        <br>
                    </td>
                </tr>

                {{-- Copyright --}}
                <tr>
                    <td width="75%" align="center">
                        <br>
                        &copy; OpenLitterMap & Contributors {{ date('Y') }}.
                        <br/>
                        <br/>
                    </td>
                </tr>

                {{-- Links to Social Media --}}
                <tr>
                    <td align="center">
                        <table border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td>
                                    <a href="http://www.facebook.com/openlittermap">
                                        <img src="https://openlittermap.com/assets/icons/facebook2.png" alt="" width="38" height="38" style="display: block;" border="0" />
                                    </a>
                                </td>
                                <td style="font-size: 0; line-height: 0;" width="20">
                                    &nbsp;
                                </td>
                                <td>
                                    <a href="http://www.twitter.com/openlittermap">
                                        <img src="https://openlittermap.com/assets/icons/twitter2.png" alt="" width="38" height="38" style="display: block;" border="0" />
                                    </a>
                                </td>
                                <td style="font-size: 0; line-height: 0;" width="20">
                                    &nbsp;
                                </td>
                                <td>
                                    <a href="http://www.instagram.com/openlittermap">
                                        <img src="https://openlittermap.com/assets/icons/ig2.png" alt="" width="38" height="38" style="display: block;" border="0" />
                                    </a>
                                </td>
                                <td style="font-size: 0; line-height: 0;" width="20">
                                    &nbsp;
                                </td>
                                <td>
                                    <a href="http://www.reddit.com/r/openlittermap">
                                        <img src="https://openlittermap.com/assets/icons/reddit.png" alt="" width="38" height="38" style="display: block;" border="0" />
                                    </a>
                                </td>
                                <td style="font-size: 0; line-height: 0;" width="20">
                                    &nbsp;
                                </td>
                                <td>
                                    <a href="https://openlittermap.tumblr.com/">
                                        <img src="https://openlittermap.com/assets/icons/tumblr.png" alt="" width="38" height="38" style="display: block;" border="0" />
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
