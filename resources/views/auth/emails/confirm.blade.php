<!DOCTYPE html>
<html lang="en">
<head>
    <title>Welcome to OpenLitterMap</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <style type="text/css">
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; }

        a[x-apple-data-detectors] {
            color: inherit !important;
            text-decoration: none !important;
            font-size: inherit !important;
            font-family: inherit !important;
            font-weight: inherit !important;
            line-height: inherit !important;
        }

        @media screen and (max-width: 525px) {
            .wrapper { width: 100% !important; max-width: 100% !important; }
            .responsive-table { width: 100% !important; }
            .mobile-padding { padding: 20px 24px !important; }
            .mobile-button { padding: 16px 24px !important; display: block !important; font-size: 18px !important; }
            .step-icon { font-size: 32px !important; }
            .hero-creatures { font-size: 20px !important; letter-spacing: 6px !important; }
            .scene-row { font-size: 14px !important; }
        }

        div[style*="margin: 16px 0;"] { margin: 0 !important; }
    </style>
    <!--[if gte mso 12]>
    <style type="text/css">
        .mso-right { padding-left: 20px; }
    </style>
    <![endif]-->
</head>
<body style="margin: 0 !important; padding: 0 !important; background-color: #f0ebe3;">

<!-- Preheader -->
<div style="display: none; font-size: 1px; color: #faf3e0; line-height: 1px; font-family: Georgia, 'Times New Roman', serif; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
    Welcome to the forest, {{ '@' . $user->username }}! Verify your email to start mapping litter and protecting nature.
</div>

<!-- Outer wrapper -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f0ebe3;">
    <tr>
        <td align="center" style="padding: 24px 12px;">
            <table align="center" border="0" cellspacing="0" cellpadding="0" width="560" class="responsive-table" style="max-width: 560px;">

                {{-- ═══════ FOREST CANOPY HEADER ═══════ --}}
                <tr>
                    <td style="background-color: #1a472a; border-radius: 16px 16px 0 0; padding: 0;">
                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                            {{-- Sky with sun --}}
                            <tr>
                                <td align="center" style="padding: 32px 40px 0 40px; background-color: #1a472a; border-radius: 16px 16px 0 0;" class="mobile-padding">
                                    <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                        <tr>
                                            <td align="center" style="font-size: 48px; line-height: 1;">
                                                &#x2600;&#xFE0F;
                                            </td>
                                        </tr>
                                        <tr>
                                            <td align="center" style="padding-top: 8px; font-size: 16px; letter-spacing: 12px; line-height: 1;" class="hero-creatures">
                                                &#x1F333; &#x1F43F;&#xFE0F; &#x1F333; &#x1F98A; &#x1F333; &#x1F994; &#x1F333;
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            {{-- Title --}}
                            <tr>
                                <td align="center" style="padding: 24px 40px 8px 40px;" class="mobile-padding">
                                    <span style="font-family: Georgia, 'Times New Roman', serif; font-size: 28px; color: #f5c542; font-weight: bold; letter-spacing: 0.5px;">
                                        Welcome to the Forest
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="padding: 0 40px 28px 40px;" class="mobile-padding">
                                    <span style="font-family: Georgia, 'Times New Roman', serif; font-size: 15px; color: #a8d5a2; letter-spacing: 0.3px;">
                                        OpenLitterMap
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- ═══════ GREETING + VERIFY CTA ═══════ --}}
                <tr>
                    <td style="background-color: #faf3e0; padding: 0;">
                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                            {{-- Username greeting --}}
                            <tr>
                                <td align="center" style="padding: 40px 40px 8px 40px;" class="mobile-padding">
                                    <span style="font-family: Georgia, 'Times New Roman', serif; font-size: 22px; color: #3a2e1f;">
                                        Hello, <strong style="color: #1a472a;">{{ '@' . $user->username }}</strong>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="padding: 8px 40px 0 40px;" class="mobile-padding">
                                    <span style="font-family: Georgia, 'Times New Roman', serif; font-size: 16px; color: #6b5c4d; line-height: 1.6;">
                                        The creatures of the forest are delighted you've arrived.
                                        <br>
                                        Together, we can keep every meadow, stream and woodland clean.
                                    </span>
                                </td>
                            </tr>

                            {{-- Creature scene divider --}}
                            <tr>
                                <td align="center" style="padding: 24px 0; font-size: 16px; letter-spacing: 8px; color: #a8d5a2;" class="scene-row">
                                    &#x1F98B; &#x1F33F; &#x1F41D; &#x1F33F; &#x1F426; &#x1F33F; &#x1F98B;
                                </td>
                            </tr>

                            {{-- Verify CTA --}}
                            <tr>
                                <td align="center" style="padding: 0 40px;" class="mobile-padding">
                                    <span style="font-family: Georgia, 'Times New Roman', serif; font-size: 14px; color: #8b7355; text-transform: uppercase; letter-spacing: 2px;">
                                        First things first
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="padding: 16px 40px 0 40px;" class="mobile-padding">
                                    <table border="0" cellspacing="0" cellpadding="0">
                                        <tr>
                                            <td align="center" style="border-radius: 10px; background-color: #2d7a3a;">
                                                <a href="{{ route('confirm-email-token', $user->token) }}"
                                                   target="_blank"
                                                   style="font-family: Georgia, 'Times New Roman', serif; font-size: 17px; color: #ffffff; text-decoration: none; padding: 16px 40px; display: inline-block; font-weight: bold; letter-spacing: 0.3px;"
                                                   class="mobile-button">
                                                    Verify Your Email &amp; Enter the Forest &rarr;
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="padding: 12px 40px 36px 40px;" class="mobile-padding">
                                    <span style="font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #b3a28e;">
                                        Confirming your email lets you start uploading photos right away.
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- ═══════ WOODLAND DIVIDER ═══════ --}}
                <tr>
                    <td style="background-color: #e8ddc8; padding: 0; height: 3px; font-size: 0; line-height: 0;">
                        &nbsp;
                    </td>
                </tr>

                {{-- ═══════ HOW IT WORKS — 3 STEPS ═══════ --}}
                <tr>
                    <td style="background-color: #f4eed8; padding: 0;">
                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                            <tr>
                                <td align="center" style="padding: 36px 40px 8px 40px;" class="mobile-padding">
                                    <span style="font-family: Georgia, 'Times New Roman', serif; font-size: 20px; color: #3a2e1f; font-weight: bold;">
                                        How You Can Help
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="padding: 4px 40px 28px 40px;" class="mobile-padding">
                                    <span style="font-family: Georgia, 'Times New Roman', serif; font-size: 14px; color: #8b7355;">
                                        Three simple steps to protect the places wildlife calls home
                                    </span>
                                </td>
                            </tr>

                            {{-- Step 1 --}}
                            <tr>
                                <td style="padding: 0 40px 20px 40px;" class="mobile-padding">
                                    <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                        <tr>
                                            <td width="56" valign="top" style="padding-right: 16px;">
                                                <div style="width: 48px; height: 48px; border-radius: 50%; background-color: #2d7a3a; text-align: center; line-height: 48px; font-size: 22px;" class="step-icon">
                                                    &#x1F4F7;
                                                </div>
                                            </td>
                                            <td valign="top">
                                                <span style="font-family: Georgia, 'Times New Roman', serif; font-size: 17px; color: #1a472a; font-weight: bold;">
                                                    1. Spot &amp; Snap
                                                </span>
                                                <br>
                                                <span style="font-family: Helvetica, Arial, sans-serif; font-size: 14px; color: #6b5c4d; line-height: 1.5;">
                                                    See litter on a walk? Take a photo. Your phone's GPS tags the location automatically.
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            {{-- Step 2 --}}
                            <tr>
                                <td style="padding: 0 40px 20px 40px;" class="mobile-padding">
                                    <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                        <tr>
                                            <td width="56" valign="top" style="padding-right: 16px;">
                                                <div style="width: 48px; height: 48px; border-radius: 50%; background-color: #c48a2a; text-align: center; line-height: 48px; font-size: 22px;" class="step-icon">
                                                    &#x1F3F7;&#xFE0F;
                                                </div>
                                            </td>
                                            <td valign="top">
                                                <span style="font-family: Georgia, 'Times New Roman', serif; font-size: 17px; color: #1a472a; font-weight: bold;">
                                                    2. Tag What You See
                                                </span>
                                                <br>
                                                <span style="font-family: Helvetica, Arial, sans-serif; font-size: 14px; color: #6b5c4d; line-height: 1.5;">
                                                    Identify the litter &mdash; cigarette butt, plastic bottle, food wrapper. Every tag builds the dataset.
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            {{-- Step 3 --}}
                            <tr>
                                <td style="padding: 0 40px 12px 40px;" class="mobile-padding">
                                    <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                        <tr>
                                            <td width="56" valign="top" style="padding-right: 16px;">
                                                <div style="width: 48px; height: 48px; border-radius: 50%; background-color: #3a7ca5; text-align: center; line-height: 48px; font-size: 22px;" class="step-icon">
                                                    &#x1F30D;
                                                </div>
                                            </td>
                                            <td valign="top">
                                                <span style="font-family: Georgia, 'Times New Roman', serif; font-size: 17px; color: #1a472a; font-weight: bold;">
                                                    3. Upload &amp; Map It
                                                </span>
                                                <br>
                                                <span style="font-family: Helvetica, Arial, sans-serif; font-size: 14px; color: #6b5c4d; line-height: 1.5;">
                                                    Your photo appears on the global map. Researchers, councils and communities use this open data to clean up the planet.
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            {{-- Creature scene --}}
                            <tr>
                                <td align="center" style="padding: 16px 0 32px 0; font-size: 16px; letter-spacing: 6px; color: #a8d5a2;" class="scene-row">
                                    &#x1F407; &#x1F33B; &#x1F98E; &#x1F33C; &#x1F43F;&#xFE0F; &#x1F33B; &#x1F41E;
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- ═══════ WHY IT MATTERS ═══════ --}}
                <tr>
                    <td style="background-color: #faf3e0; padding: 0;">
                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                            <tr>
                                <td align="center" style="padding: 36px 40px 12px 40px;" class="mobile-padding">
                                    <span style="font-family: Georgia, 'Times New Roman', serif; font-size: 20px; color: #3a2e1f; font-weight: bold;">
                                        Why It Matters
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="padding: 0 40px 32px 40px;" class="mobile-padding">
                                    <span style="font-family: Georgia, 'Times New Roman', serif; font-size: 15px; color: #6b5c4d; line-height: 1.7;">
                                        OpenLitterMap is an open-source, UN-endorsed Digital Public Good.
                                        Over 500,000 uploads from 110+ countries are already on the map,
                                        cited in 98+ peer-reviewed research papers.
                                        <br><br>
                                        Every photo you contribute is open data &mdash; free for scientists,
                                        policymakers and communities to use. Small actions add up.
                                        The hedgehogs, foxes and owls of this world are counting on us.
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- ═══════ COMMUNITY SECTION ═══════ --}}
                <tr>
                    <td style="background-color: #e8ddc8; padding: 0; height: 3px; font-size: 0; line-height: 0;">
                        &nbsp;
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #f4eed8; padding: 0;">
                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                            <tr>
                                <td align="center" style="padding: 32px 40px 8px 40px;" class="mobile-padding">
                                    <span style="font-family: Georgia, 'Times New Roman', serif; font-size: 18px; color: #3a2e1f; font-weight: bold;">
                                        Join the Community &#x1F9D1;&#x200D;&#x1F91D;&#x200D;&#x1F9D1;
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="padding: 4px 40px 20px 40px;" class="mobile-padding">
                                    <span style="font-family: Georgia, 'Times New Roman', serif; font-size: 14px; color: #6b5c4d; line-height: 1.6;">
                                        Chat with other nature guardians on Slack, or join our weekly
                                        community call where we discuss everything from app features
                                        to grant applications.
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="padding: 0 40px;" class="mobile-padding">
                                    <table border="0" cellspacing="0" cellpadding="0">
                                        <tr>
                                            <td align="center" style="border-radius: 8px; background-color: #4A154B;">
                                                <a href="https://join.slack.com/t/openlittermap/shared_invite/zt-fdctasud-mu~OBQKReRdC9Ai9KgGROw"
                                                   target="_blank"
                                                   style="font-family: Helvetica, Arial, sans-serif; font-size: 14px; color: #ffffff; text-decoration: none; padding: 12px 28px; display: inline-block; font-weight: bold;"
                                                   class="mobile-button">
                                                    Join us on Slack
                                                </a>
                                            </td>
                                            <td width="12">&nbsp;</td>
                                            <td align="center" style="border-radius: 8px; background-color: #1a472a;">
                                                <a href="https://openlittermap.com/community"
                                                   target="_blank"
                                                   style="font-family: Helvetica, Arial, sans-serif; font-size: 14px; color: #ffffff; text-decoration: none; padding: 12px 28px; display: inline-block; font-weight: bold;"
                                                   class="mobile-button">
                                                    Community
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 24px 0 0 0; font-size: 0; line-height: 0;">
                                    &nbsp;
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- ═══════ FOREST FLOOR FOOTER ═══════ --}}
                <tr>
                    <td style="background-color: #1a472a; border-radius: 0 0 16px 16px; padding: 0;">
                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                            {{-- Creature footer scene --}}
                            <tr>
                                <td align="center" style="padding: 28px 40px 8px 40px; font-size: 18px; letter-spacing: 8px;" class="mobile-padding">
                                    &#x1F989; &#x1F343; &#x1F98A; &#x1F343; &#x1F994; &#x1F343; &#x1F407;
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="padding: 8px 40px 4px 40px;" class="mobile-padding">
                                    <span style="font-family: Georgia, 'Times New Roman', serif; font-size: 13px; color: #a8d5a2; font-style: italic;">
                                        "The forest is counting on every one of us."
                                    </span>
                                </td>
                            </tr>

                            {{-- Social links --}}
                            <tr>
                                <td align="center" style="padding: 20px 40px 8px 40px;" class="mobile-padding">
                                    <table border="0" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td style="padding: 0 8px;">
                                                <a href="https://www.facebook.com/openlittermap" target="_blank" style="font-family: Helvetica, Arial, sans-serif; font-size: 13px; color: #a8d5a2; text-decoration: none;">Facebook</a>
                                            </td>
                                            <td style="color: #4a7c59;">&middot;</td>
                                            <td style="padding: 0 8px;">
                                                <a href="https://www.instagram.com/openlittermap" target="_blank" style="font-family: Helvetica, Arial, sans-serif; font-size: 13px; color: #a8d5a2; text-decoration: none;">Instagram</a>
                                            </td>
                                            <td style="color: #4a7c59;">&middot;</td>
                                            <td style="padding: 0 8px;">
                                                <a href="https://www.reddit.com/r/openlittermap" target="_blank" style="font-family: Helvetica, Arial, sans-serif; font-size: 13px; color: #a8d5a2; text-decoration: none;">Reddit</a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            {{-- Copyright --}}
                            <tr>
                                <td align="center" style="padding: 12px 40px 28px 40px;" class="mobile-padding">
                                    <span style="font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #5a8a5c;">
                                        &copy; OpenLitterMap &amp; Contributors {{ date('Y') }}
                                        <br>
                                        UN-endorsed Digital Public Good
                                    </span>
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
