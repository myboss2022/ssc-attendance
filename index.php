<?php
require_once __DIR__ . '/config.php';
$conn = db();

$organizationName = get_ssc_setting('organization_name', 'SSCAttendance');
$event = active_event($conn);
$studentCount = (int) ($conn->query('SELECT COUNT(*) c FROM students')->fetch_assoc()['c'] ?? 0);
$eventCount = (int) ($conn->query('SELECT COUNT(*) c FROM events')->fetch_assoc()['c'] ?? 0);
$attendanceToday = (int) ($conn->query('SELECT COUNT(*) c FROM event_attendance WHERE recorded_date = CURDATE()')->fetch_assoc()['c'] ?? 0);
$entriesToday = (int) ($conn->query('SELECT COUNT(*) c FROM event_attendance WHERE scan_in IS NOT NULL AND recorded_date = CURDATE()')->fetch_assoc()['c'] ?? 0);
$exitsToday = (int) ($conn->query('SELECT COUNT(*) c FROM event_attendance WHERE scan_out IS NOT NULL AND recorded_date = CURDATE()')->fetch_assoc()['c'] ?? 0);
$incomingEvent = $conn->query("SELECT event_name, event_date, start_time, venue FROM events WHERE event_date >= CURDATE() AND status IN ('UPCOMING','DRAFT') ORDER BY event_date ASC, start_time ASC LIMIT 1")->fetch_assoc();
$todayEvent = $conn->query("SELECT event_name, status, venue, event_date, start_time FROM events WHERE event_date = CURDATE() AND status IN ('ACTIVE','UPCOMING','DRAFT') ORDER BY FIELD(status,'ACTIVE','UPCOMING','DRAFT'), start_time LIMIT 1")->fetch_assoc();
$displayEvent = $event ?: $todayEvent;
$attendRate = $studentCount > 0 ? min(100, (int) round(($attendanceToday / $studentCount) * 100)) : 0;
$dbConnected = $conn && $conn->ping();
$announcements = get_active_announcements($conn);
$tickerSpeed = max(12, min(80, (int) get_ssc_setting('ticker_speed', '24')));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo h($organizationName); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        html { scroll-behavior: smooth; }
        body { font-family: Arial, Helvetica, sans-serif; overflow-x: hidden; padding-bottom: 5rem; }
        .bg-grid {
            background-image: linear-gradient(rgba(148,163,184,0.15) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(148,163,184,0.15) 1px, transparent 1px);
            background-size: 26px 26px;
        }
        .glow-circle {
            position: absolute;
            border-radius: 9999px;
            filter: blur(72px);
            opacity: 0.55;
        }
        .card-float { animation: floatCard 6s ease-in-out infinite; }
        .card-float.delay { animation-delay: 1.5s; }
        .float-soft { animation: floatSoft 8s ease-in-out infinite; }
        .news-broadcast {
            display: flex;
            align-items: stretch;
            width: calc(100% - 1rem);
            max-width: 1500px;
            margin: 0 auto;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 1rem 1rem 0 0;
            overflow: hidden;
            background: rgba(248, 250, 252, 0.95);
            box-shadow: 0 -12px 30px rgba(15, 23, 42, 0.08);
        }
        .news-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 150px;
            padding: 0.5rem 0.8rem;
            background: linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%);
            border-right: 1px solid rgba(15, 23, 42, 0.08);
        }
        .news-brand-inner {
            display: flex;
            flex-direction: column;
            line-height: 0.9;
            text-transform: uppercase;
        }
        .news-brand-line {
            font-size: 1.2rem;
            font-weight: 900;
            letter-spacing: -0.06em;
            color: #0f172a;
        }
        .news-brand-line .accent { color: #dc2626; }
        .news-brand-sub {
            font-size: 0.52rem;
            letter-spacing: 0.18em;
            font-weight: 800;
            color: #475569;
            margin-top: 0.22rem;
        }
        .news-ticker-wrap {
            position: relative;
            flex: 1;
            overflow: hidden;
            background: linear-gradient(90deg, #ef4444 0%, #b91c1c 100%);
        }
        .news-ticker-track {
            display: inline-flex;
            align-items: center;
            white-space: nowrap;
            min-width: max-content;
            padding-left: 0.5rem;
            animation: tickerMove <?php echo $tickerSpeed; ?>s linear infinite;
        }
        .news-ticker-item {
            display: inline-flex;
            align-items: center;
            gap: 0.7rem;
            padding: 0.65rem 0.75rem;
            color: white;
        }
        .news-ticker-tag {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            background: rgba(255,255,255,0.16);
            padding: 0.2rem 0.5rem;
            font-size: 0.58rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }
        .news-ticker-title { font-size: 0.88rem; font-weight: 800; }
        .news-ticker-divider { opacity: 0.75; }
        .news-ticker-message { font-size: 0.8rem; font-weight: 700; opacity: 0.97; }

        @keyframes floatCard { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-10px); } }
        @keyframes floatSoft { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-8px); } }
        @keyframes tickerMove { from { transform: translateX(0); } to { transform: translateX(-50%); } }

        @media (max-width: 640px) {
            body { padding-bottom: 5.5rem; }
            main h1 {
                font-size: clamp(2.1rem, 9vw, 3rem) !important;
                letter-spacing: -0.06em;
            }
            main h2 {
                font-size: clamp(1.7rem, 7vw, 2.4rem) !important;
                letter-spacing: -0.05em;
            }
            main h3 {
                font-size: clamp(1.2rem, 5vw, 1.8rem) !important;
            }
            main p,
            main li,
            main span,
            main a {
                word-break: break-word;
            }
            .news-broadcast {
                width: calc(100% - 0.5rem);
                border-radius: 0.8rem 0.8rem 0 0;
            }
            .news-brand {
                width: 92px;
                padding: 0.45rem 0.5rem;
            }
            .news-brand-line {
                font-size: 0.82rem;
            }
            .news-brand-sub {
                font-size: 0.38rem;
                letter-spacing: 0.1em;
            }
            .news-ticker-item {
                gap: 0.35rem;
                padding: 0.52rem 0.5rem;
            }
            .news-ticker-tag {
                font-size: 0.46rem;
                letter-spacing: 0.08em;
                padding: 0.18rem 0.36rem;
            }
            .news-ticker-title {
                font-size: 0.7rem;
            }
            .news-ticker-message {
                font-size: 0.62rem;
            }
            .floating-chip {
                display: none !important;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
    <style>
    .power-stage{position:relative;width:20rem;height:20rem;isolation:isolate}.power-stage:before{content:"";position:absolute;inset:8%;border-radius:50%;background:radial-gradient(circle,rgba(14,165,233,.3),rgba(20,184,166,.14) 35%,transparent 70%);filter:blur(18px);animation:powerPulse 2.4s ease-in-out infinite}.power-smoke{position:absolute;inset:-8%;border:1px solid rgba(125,211,252,.24);border-radius:44% 56% 52% 48%;background:conic-gradient(from 25deg,transparent,rgba(14,165,233,.28),transparent 28%,rgba(20,184,166,.28),transparent 62%,rgba(59,130,246,.24),transparent);filter:blur(9px);animation:smokeCompress 3.8s cubic-bezier(.7,0,.2,1) infinite}.power-smoke:nth-child(2){inset:-3%;animation-delay:-1.25s;transform:rotate(55deg);opacity:.8}.power-smoke:nth-child(3){inset:2%;animation-delay:-2.45s;transform:rotate(125deg);opacity:.7}.power-beam{position:absolute;top:50%;left:4%;right:4%;height:.45rem;transform:translateY(-50%) scaleX(.15);border-radius:999px;background:linear-gradient(90deg,transparent,#38bdf8 20%,#fde047 50%,#f97316 80%,transparent);box-shadow:0 0 12px #38bdf8,0 0 28px #facc15,0 0 52px rgba(249,115,22,.75);animation:beamSurge 2.2s ease-in-out infinite}.power-flash{position:absolute;inset:31%;border-radius:50%;background:radial-gradient(circle,#fff 0 8%,#fde047 20%,#f97316 42%,transparent 72%);filter:blur(2px);opacity:0;animation:flashBurst 2.2s ease-out infinite}.power-lightning{position:absolute;left:50%;top:50%;width:5rem;height:8rem;transform:translate(-50%,-50%);background:linear-gradient(112deg,transparent 40%,#fff 43%,#facc15 48%,transparent 53%),linear-gradient(68deg,transparent 44%,#fb923c 48%,#fff 51%,transparent 56%);filter:drop-shadow(0 0 7px #facc15) drop-shadow(0 0 18px #f97316);opacity:0;animation:lightningFlash 2.2s ease-out infinite}.power-ring{position:absolute;inset:22%;border:2px solid rgba(56,189,248,.5);border-radius:50%;box-shadow:0 0 26px rgba(14,165,233,.25),inset 0 0 20px rgba(20,184,166,.2);animation:ringCollapse 2.8s ease-in-out infinite}.power-ring:nth-of-type(5){inset:30%;animation-delay:-1.4s;border-color:rgba(45,212,191,.55);transform:rotate(55deg) scaleX(.8)}.power-particle{position:absolute;left:50%;top:50%;width:.3rem;height:2.8rem;border-radius:999px;background:linear-gradient(to bottom,transparent,#67e8f9,transparent);transform-origin:50% 0;animation:particlePull 2.2s ease-in infinite}.power-particle:nth-of-type(6){transform:rotate(25deg);animation-delay:-.2s}.power-particle:nth-of-type(7){transform:rotate(115deg);animation-delay:-.8s}.power-particle:nth-of-type(8){transform:rotate(205deg);animation-delay:-1.4s}.power-particle:nth-of-type(9){transform:rotate(295deg);animation-delay:-1.9s}.power-core{position:absolute;inset:37%;display:flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.5);border-radius:1rem;background:linear-gradient(135deg,#2563eb,#06b6d4);box-shadow:0 0 0 8px rgba(56,189,248,.1),0 0 36px rgba(14,165,233,.8),0 0 70px rgba(20,184,166,.38);animation:coreSurge 1.8s ease-in-out infinite}.power-core:after{content:"";position:absolute;inset:-18px;border:2px solid rgba(103,232,249,.65);border-radius:1.3rem;animation:shockwave 1.8s ease-out infinite}.power-core span{position:relative;z-index:1;font-size:1.9rem;font-weight:900;color:#fff;text-shadow:0 2px 10px rgba(7,89,133,.7)}@keyframes smokeCompress{0%,100%{transform:scale(1.3) rotate(0deg);opacity:.2}48%{transform:scale(.5) rotate(180deg);opacity:1}65%{transform:scale(.7) rotate(240deg);opacity:.65}}@keyframes beamSurge{0%,100%{transform:translateY(-50%) scaleX(.1);opacity:.15}48%,62%{transform:translateY(-50%) scaleX(1);opacity:1}}@keyframes flashBurst{0%,42%,100%{opacity:0;transform:scale(.4)}52%{opacity:1;transform:scale(1.25)}68%{opacity:.15;transform:scale(1.7)}}@keyframes lightningFlash{0%,47%,100%{opacity:0;transform:translate(-50%,-50%) scale(.6)}53%,62%{opacity:1;transform:translate(-50%,-50%) scale(1.1)}70%{opacity:0;transform:translate(-50%,-50%) scale(1.35)}}@keyframes ringCollapse{0%,100%{transform:scale(1.18);opacity:.25}52%{transform:scale(.72);opacity:1}70%{transform:scale(.9);opacity:.55}}@keyframes particlePull{0%{transform:rotate(0deg) translateY(-5.8rem) scaleY(1);opacity:0}25%{opacity:1}100%{transform:rotate(0deg) translateY(-1.5rem) scaleY(.35);opacity:0}}@keyframes powerPulse{50%{transform:scale(1.25);opacity:.75}}@keyframes coreSurge{50%{transform:scale(1.08);box-shadow:0 0 0 12px rgba(56,189,248,.14),0 0 55px rgba(14,165,233,1),0 0 90px rgba(20,184,166,.5)}}@keyframes shockwave{0%{transform:scale(.7);opacity:.9}100%{transform:scale(1.8);opacity:0}}@media(prefers-reduced-motion:reduce){.power-stage *,.power-stage:before{animation:none!important}}
    </style>
<style>
.power-clash{position:absolute;top:50%;left:50%;z-index:2;width:100%;height:5rem;transform:translate(-50%,-50%);pointer-events:none}.clash-beam{position:absolute;top:50%;width:58%;height:.7rem;border-radius:999px;filter:blur(2px);opacity:.9}.clash-beam:after{content:"";position:absolute;inset:-.45rem;filter:blur(8px);opacity:.8}.clash-blue{right:48%;transform-origin:right center;background:linear-gradient(90deg,transparent,#38bdf8 55%,#dbeafe);box-shadow:0 0 12px #38bdf8,0 0 28px #0ea5e9;animation:clashBlue 2.8s ease-in-out infinite}.clash-blue:after{background:#38bdf8}.clash-gold{left:48%;transform-origin:left center;background:linear-gradient(90deg,#fff7ed,#facc15 45%,transparent);box-shadow:0 0 12px #facc15,0 0 28px #f97316;animation:clashGold 2.8s ease-in-out infinite}.clash-gold:after{background:#facc15}.clash-orb{position:absolute;top:50%;left:50%;z-index:3;width:3.3rem;height:3.3rem;border:2px solid #fff;border-radius:50%;background:radial-gradient(circle,#fff 0 8%,#fde047 24%,#f97316 48%,rgba(249,115,22,.15) 70%,transparent 72%);box-shadow:0 0 12px #fff,0 0 32px #facc15,0 0 66px #f97316;transform:translate(-50%,-50%) scale(.45);animation:clashOrb 2.8s ease-in-out infinite}.clash-orb:after{content:"";position:absolute;inset:-1.2rem;border:2px solid rgba(253,224,71,.75);border-radius:50%;animation:clashBurst 2.8s ease-out infinite}.clash-shockwave{position:absolute;top:50%;left:50%;width:4rem;height:4rem;border:3px solid rgba(251,191,36,.9);border-radius:50%;transform:translate(-50%,-50%) scale(.2);opacity:0;animation:clashShockwave 2.8s ease-out infinite}@keyframes clashBlue{0%,100%{transform:scaleX(.18);opacity:.1}42%,57%{transform:scaleX(1);opacity:1}64%{transform:scaleX(.45);opacity:.35}}@keyframes clashGold{0%,100%{transform:scaleX(.18);opacity:.1}42%,57%{transform:scaleX(1);opacity:1}64%{transform:scaleX(.45);opacity:.35}}@keyframes clashOrb{0%,35%,100%{transform:translate(-50%,-50%) scale(.35);opacity:.2}48%{transform:translate(-50%,-50%) scale(1.35);opacity:1}58%{transform:translate(-50%,-50%) scale(.85);opacity:1}65%{transform:translate(-50%,-50%) scale(1.9);opacity:0}}@keyframes clashBurst{0%,40%{transform:scale(.35);opacity:0}50%{transform:scale(1);opacity:1}68%,100%{transform:scale(2.4);opacity:0}}@keyframes clashShockwave{0%,43%{transform:translate(-50%,-50%) scale(.2);opacity:0}52%{opacity:1}76%,100%{transform:translate(-50%,-50%) scale(3.2);opacity:0}}@media(prefers-reduced-motion:reduce){.power-clash *{animation:none!important}}
.power-stage:has(.clash-ball){animation:stageImpact 2.8s ease-in-out infinite}.clash-beam{opacity:.12!important}.clash-ball{position:absolute;top:50%;z-index:4;width:2.8rem;height:2.8rem;border-radius:50%;transform:translateY(-50%);opacity:0}.clash-ball-blue{left:4%;background:radial-gradient(circle at 35% 30%,#e0f2fe,#38bdf8 35%,#0369a1 72%);box-shadow:0 0 12px #7dd3fc,0 0 30px #0ea5e9,0 0 58px rgba(14,165,233,.85);animation:ballBlue 2.8s cubic-bezier(.2,.8,.2,1) infinite}.clash-ball-gold{right:4%;background:radial-gradient(circle at 35% 30%,#fff7ed,#fde047 35%,#ea580c 72%);box-shadow:0 0 12px #fef08a,0 0 30px #facc15,0 0 58px rgba(249,115,22,.85);animation:ballGold 2.8s cubic-bezier(.2,.8,.2,1) infinite}.clash-ball:after{content:"";position:absolute;inset:-.7rem;border:2px solid currentColor;border-radius:50%;opacity:.65;filter:blur(2px);animation:ballHalo 2.8s ease-in-out infinite}.clash-ball-blue{color:#38bdf8}.clash-ball-gold{color:#facc15}.power-clash:after{content:"";position:absolute;top:50%;left:50%;width:5rem;height:5rem;border:3px solid #fde047;border-radius:50%;background:radial-gradient(circle,#fff,#fde047 18%,#f97316 42%,transparent 70%);box-shadow:0 0 25px #facc15,0 0 65px #f97316;transform:translate(-50%,-50%) scale(.1);opacity:0;animation:impactExplosion 2.8s ease-out infinite}.clash-shockwave{animation:impactShockwave 2.8s ease-out infinite}@keyframes ballBlue{0%,12%{left:4%;transform:translateY(-50%) scale(.35);opacity:0}22%{opacity:1}47%{left:44%;transform:translateY(-50%) scale(1.05);opacity:1}53%,100%{left:50%;transform:translateY(-50%) scale(.15);opacity:0}}@keyframes ballGold{0%,12%{right:4%;transform:translateY(-50%) scale(.35);opacity:0}22%{opacity:1}47%{right:44%;transform:translateY(-50%) scale(1.05);opacity:1}53%,100%{right:50%;transform:translateY(-50%) scale(.15);opacity:0}}@keyframes ballHalo{0%,20%{transform:scale(.5);opacity:0}35%{opacity:1}52%,100%{transform:scale(1.7);opacity:0}}@keyframes impactExplosion{0%,45%{transform:translate(-50%,-50%) scale(.1);opacity:0}50%{transform:translate(-50%,-50%) scale(1);opacity:1}62%{transform:translate(-50%,-50%) scale(2.5);opacity:.8}78%,100%{transform:translate(-50%,-50%) scale(4.2);opacity:0}}@keyframes impactShockwave{0%,45%{transform:translate(-50%,-50%) scale(.1);opacity:0}52%{opacity:1}82%,100%{transform:translate(-50%,-50%) scale(4.5);opacity:0}}@keyframes stageImpact{0%,45%,100%{transform:translateY(0) rotate(0)}49%{transform:translateY(-2px) rotate(-1deg)}52%{transform:translateY(2px) rotate(1deg)}55%{transform:translateY(-1px) rotate(0)}}
</style>
<style>
    .power-stage{border-color:transparent!important;background:transparent!important;box-shadow:none!important;animation:stageReveal 4.8s ease-in-out infinite}
    .power-stage:before{opacity:0;animation:pressureGlow 4.8s ease-in-out infinite}
    .power-stage>.absolute.inset-6{z-index:1;opacity:0;transform:scale(.72);animation:boxReveal 4.8s cubic-bezier(.2,.8,.2,1) infinite}
    .power-stage .power-smoke{z-index:4;animation:airCompression 4.8s cubic-bezier(.55,0,.2,1) infinite!important}
    .power-stage .power-smoke:nth-child(2){animation-delay:0s!important}
    .power-stage .power-smoke:nth-child(3){animation-delay:0s!important}
    .power-stage .power-beam,.power-stage .power-lightning,.power-stage .power-clash,.power-stage .power-ring,.power-stage .power-particle{opacity:0;animation-delay:2.45s!important}
    .power-stage .power-flash{z-index:6;animation:pressureBurst 4.8s ease-out infinite!important}
    .power-stage .power-core{z-index:7;opacity:0;animation:coreReveal 4.8s ease-out infinite}
    @keyframes airCompression{0%,12%{opacity:0;transform:scale(1.35) rotate(0deg)}20%{opacity:.55}42%{opacity:.82;transform:scale(.42) rotate(140deg)}52%{opacity:1;transform:scale(.08) rotate(300deg)}59%{opacity:0;transform:scale(1.9) rotate(360deg)}100%{opacity:0}}
    @keyframes pressureGlow{0%,46%{opacity:0;transform:scale(.65)}52%{opacity:.95;transform:scale(.9)}62%,100%{opacity:0;transform:scale(1.35)}}
    @keyframes pressureBurst{0%,48%{opacity:0;transform:scale(.1)}54%{opacity:1;transform:scale(1.15)}66%,100%{opacity:0;transform:scale(2.1)}}
    @keyframes boxReveal{0%,53%{opacity:0;transform:scale(.72)}66%{opacity:1;transform:scale(1.03)}75%,100%{opacity:1;transform:scale(1)}}
    @keyframes coreReveal{0%,68%{opacity:0;transform:scale(.35)}80%{opacity:1;transform:scale(1.08)}88%,100%{opacity:1;transform:scale(1)}}
    @keyframes stageReveal{0%,52%{filter:brightness(1)}57%{filter:brightness(2.2)}68%,100%{filter:brightness(1)}}
    @media(prefers-reduced-motion:reduce){.power-stage,.power-stage:before,.power-stage>.absolute.inset-6,.power-stage .power-smoke,.power-stage .power-flash,.power-stage .power-core{animation:none!important}.power-stage{border-color:rgba(255,255,255,.1)!important;background:linear-gradient(135deg,rgba(59,130,246,.1),#0f172a,rgba(20,184,166,.1))!important}.power-stage>.absolute.inset-6,.power-stage .power-core{opacity:1;transform:none}}
</style>
</head>
<body class="bg-[#edf5ff] text-slate-800 antialiased">
    <div class="relative min-h-screen overflow-hidden bg-[radial-gradient(circle_at_top_left,_rgba(59,130,246,0.12),_transparent_32%),radial-gradient(circle_at_bottom_right,_rgba(20,184,166,0.12),_transparent_30%),linear-gradient(180deg,#f4f8ff_0%,#eef5ff_45%,#f8fbff_100%)]">
        <div class="absolute inset-0 opacity-60" style="background-image:linear-gradient(rgba(148,163,184,0.12) 1px,transparent 1px),linear-gradient(90deg,rgba(148,163,184,0.12) 1px,transparent 1px);background-size:26px 26px;"></div>
        <div class="absolute -left-16 top-0 h-72 w-72 rounded-full bg-blue-400/20 blur-3xl"></div>
        <div class="absolute right-0 top-10 h-80 w-80 rounded-full bg-cyan-400/20 blur-3xl"></div>
        <div class="absolute bottom-0 left-1/3 h-80 w-80 rounded-full bg-teal-300/20 blur-3xl"></div>

        <header class="fixed inset-x-0 top-0 z-40 border-b border-slate-200/80 bg-white/75 backdrop-blur-xl">
            <nav class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <a href="index.php" class="flex items-center gap-3">
                    <img src="logo/SSC.png" alt="SSC logo" class="h-11 w-11 rounded-2xl object-contain shadow-lg shadow-blue-500/20">
                    <div>
                        <div class="text-xl font-black tracking-tight text-slate-900">SSCAttendance</div>
                        <div class="text-[10px] uppercase tracking-[0.24em] text-slate-500">School Attendance System</div>
                    </div>
                </a>

                <div class="hidden items-center gap-7 text-sm font-semibold text-slate-700 md:flex">
                    <a href="index.php" class="rounded-full px-3 py-2 transition hover:bg-blue-50 hover:text-blue-700">Home</a>
                    <a href="index1.php" class="rounded-full px-3 py-2 transition hover:bg-blue-50 hover:text-blue-700">Attendance</a>
                    <a href="admin/events.php" class="rounded-full px-3 py-2 transition hover:bg-blue-50 hover:text-blue-700">Events</a>
                    <a href="#features" class="rounded-full px-3 py-2 transition hover:bg-blue-50 hover:text-blue-700">Features</a>
                    <a href="#about" class="rounded-full px-3 py-2 transition hover:bg-blue-50 hover:text-blue-700">About</a>
                </div>

                <button id="menuButton" type="button" onclick="var menu=document.getElementById('mobileMenu'); var isHidden=menu.classList.toggle('hidden'); this.setAttribute('aria-expanded',String(!isHidden));" class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm md:hidden" aria-label="Toggle menu" aria-controls="mobileMenu" aria-expanded="false">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/></svg>
                </button>
            </nav>

            <div id="mobileMenu" class="hidden border-t border-slate-200 bg-white md:hidden">
                <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-4 text-sm font-medium text-slate-700">
                    <a href="index.php" class="rounded-xl px-2 py-2 hover:bg-blue-50 hover:text-blue-700">Home</a>
                    <a href="index1.php" class="rounded-xl px-2 py-2 hover:bg-blue-50 hover:text-blue-700">Attendance</a>
                    <a href="admin/events.php" class="rounded-xl px-2 py-2 hover:bg-blue-50 hover:text-blue-700">Events</a>
                    <a href="#features" class="rounded-xl px-2 py-2 hover:bg-blue-50 hover:text-blue-700">Features</a>
                    <a href="#about" class="rounded-xl px-2 py-2 hover:bg-blue-50 hover:text-blue-700">About</a>
                    <a href="index1.php" class="mt-2 inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 font-semibold text-white">Open Attendance System</a>
                </div>
            </div>
        </header>

        <main class="relative z-10 pt-24">
            <section class="relative overflow-hidden">
                <div class="mx-auto grid max-w-7xl gap-10 px-4 pb-16 pt-10 sm:px-6 lg:grid-cols-[1.08fr_0.92fr] lg:px-8 lg:pb-20 lg:pt-16">
                    <div class="relative flex flex-col justify-center">
                        <div class="inline-flex w-fit items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.24em] text-blue-700 shadow-sm">
                            <span class="h-2.5 w-2.5 rounded-full bg-emerald-500 shadow-[0_0_12px_rgba(16,185,129,0.8)]"></span>
                            School Event Intelligence
                        </div>

                        <h1 class="mt-6 max-w-2xl text-4xl font-black tracking-[-0.08em] text-slate-900 sm:text-5xl lg:text-6xl">
                            Smarter attendance for
                            <span class="mt-2 block bg-gradient-to-r from-blue-700 via-sky-500 to-teal-500 bg-clip-text text-transparent">every school event</span>
                        </h1>

                        <p class="mt-5 max-w-xl text-lg leading-8 text-slate-600">
                            Fast, clear, and secure attendance tracking for school activities, competitions, events, and daily monitoring.
                        </p>

                        <div class="mt-8 flex flex-wrap items-center gap-4">
                            <a href="index1.php" class="inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-blue-700 to-sky-600 px-6 py-3 text-sm font-semibold text-white shadow-[0_15px_35px_rgba(37,99,235,0.28)] transition hover:-translate-y-0.5">
                                Start Attendance
                                <span aria-hidden="true">→</span>
                            </a>
                            <a href="#features" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:text-blue-700">
                                Explore Features
                                <span aria-hidden="true">↓</span>
                            </a>
                        </div>

                        <div class="mt-8 flex flex-wrap items-center gap-4 text-sm font-medium text-slate-600">
                            <div class="flex items-center gap-2 rounded-full border border-slate-200 bg-white/80 px-3 py-2"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>Students</div>
                            <div class="flex items-center gap-2 rounded-full border border-slate-200 bg-white/80 px-3 py-2"><span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span>Events</div>
                            <div class="flex items-center gap-2 rounded-full border border-slate-200 bg-white/80 px-3 py-2"><span class="h-2.5 w-2.5 rounded-full bg-teal-500"></span>Scanner</div>
                            <div class="flex items-center gap-2 rounded-full border border-slate-200 bg-white/80 px-3 py-2"><span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>Secure</div>
                        </div>
                    </div>

                    <div class="relative">
                        <div class="relative mx-auto max-w-xl">
                            <div class="floating-chip absolute -left-5 -top-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-700 shadow-lg shadow-emerald-900/5">✓ Verified</div>
                            <div class="floating-chip absolute -right-6 -top-5 rounded-2xl border border-violet-200 bg-white px-3 py-2 text-sm font-bold text-violet-700 shadow-lg shadow-violet-900/5">ENTRY</div>
                            <div class="floating-chip absolute -bottom-5 -left-4 rounded-2xl border border-sky-200 bg-sky-50 px-3 py-2 text-sm font-bold text-sky-700 shadow-lg shadow-sky-900/5">EVENT ACTIVE</div>
                            <div class="floating-chip absolute -bottom-5 -right-6 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm font-bold text-slate-700 shadow-lg shadow-slate-900/5"><?php echo number_format(max(0, $studentCount)); ?> Students</div>

                            <div class="overflow-hidden rounded-[2rem] border border-blue-100 bg-white shadow-[0_35px_90px_rgba(37,99,235,0.12)]">
                                <div class="flex items-center justify-between bg-gradient-to-r from-blue-700 via-sky-600 to-cyan-500 px-5 py-4 text-white">
                                    <div>
                                        <div class="text-[11px] font-bold uppercase tracking-[0.25em] text-blue-100">Live Attendance</div>
                                        <div class="mt-2 text-xl font-black text-white"><?php echo $displayEvent ? h($displayEvent['event_name']) : 'No Event Today'; ?></div>
                                    </div>
                                    <div class="inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.2em] text-white">
                                        <span class="inline-flex h-2 w-2 rounded-full bg-emerald-300"></span>
                                        <?php echo $event ? 'Live' : ($todayEvent ? h($todayEvent['status']) : 'Offline'); ?>
                                    </div>
                                </div>

                                <div class="p-5">
                                    <div class="rounded-2xl border border-blue-100 bg-blue-50 p-4">
                                        <div class="text-[11px] uppercase tracking-[0.22em] text-blue-700">Student verification</div>
                                        <div class="mt-4 flex items-center gap-3">
                                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-500 text-2xl font-black text-white shadow-sm shadow-emerald-200">✓</div>
                                            <div>
                                                <div class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Student name</div>
                                                <div class="mt-1 text-lg font-bold text-slate-900">Juan Dela Cruz</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                        <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4">
                                            <div class="text-[10px] uppercase tracking-[0.2em] text-emerald-700">Entry</div>
                                            <div class="mt-2 text-2xl font-black text-emerald-700">09:32 AM</div>
                                        </div>
                                        <div class="rounded-2xl border border-blue-100 bg-blue-50 p-4">
                                            <div class="text-[10px] uppercase tracking-[0.2em] text-blue-700">Status</div>
                                            <div class="mt-2 text-2xl font-black text-blue-700">Verified</div>
                                        </div>
                                    </div>

                                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                            <div class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Today</div>
                                            <div class="mt-2 text-2xl font-black text-slate-900"><?php echo number_format($attendanceToday); ?></div>
                                        </div>
                                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-3">
                                            <div class="text-[10px] uppercase tracking-[0.2em] text-emerald-700">Present</div>
                                            <div class="mt-2 text-2xl font-black text-emerald-700"><?php echo number_format($entriesToday); ?></div>
                                        </div>
                                        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-3">
                                            <div class="text-[10px] uppercase tracking-[0.2em] text-amber-700">Exit</div>
                                            <div class="mt-2 text-2xl font-black text-amber-700"><?php echo number_format($exitsToday); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:px-8">
                <div class="mb-8">
                    <div class="text-xs font-bold uppercase tracking-[0.22em] text-blue-700">Quick access</div>
                    <h2 class="mt-2 text-3xl font-black tracking-[-0.06em] text-slate-900">Attendance at a glance</h2>
                </div>

                <div class="grid gap-6 lg:grid-cols-3">
                    <a href="index1.php" class="group rounded-[1.8rem] border border-slate-200 bg-white p-6 shadow-[0_18px_45px_rgba(15,23,42,0.05)] transition hover:-translate-y-1 hover:shadow-[0_24px_60px_rgba(37,99,235,0.12)]">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700"><svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                        <h3 class="mt-5 text-2xl font-bold text-slate-900">Student Entry</h3>
                        <p class="mt-3 text-slate-600">Quickly record student arrivals during school events and activities.</p>
                        <div class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-emerald-700">Record Entry <span aria-hidden="true">→</span></div>
                    </a>

                    <a href="index1.php?mode=out" class="group rounded-[1.8rem] border border-slate-200 bg-white p-6 shadow-[0_18px_45px_rgba(15,23,42,0.05)] transition hover:-translate-y-1 hover:shadow-[0_24px_60px_rgba(249,115,22,0.1)]">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-100 text-amber-700"><svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M11 5l-7 7 7 7" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                        <h3 class="mt-5 text-2xl font-bold text-slate-900">Student Exit</h3>
                        <p class="mt-3 text-slate-600">Track departures and maintain an accurate event status for each student.</p>
                        <div class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-amber-700">Record Exit <span aria-hidden="true">→</span></div>
                    </a>

                    <a href="admin/events.php" class="group rounded-[1.8rem] border border-slate-200 bg-white p-6 shadow-[0_18px_45px_rgba(15,23,42,0.05)] transition hover:-translate-y-1 hover:shadow-[0_24px_60px_rgba(14,165,233,0.12)]">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-100 text-violet-700"><svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2v4M16 2v4M3 10h18M5 5h14a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                        <h3 class="mt-5 text-2xl font-bold text-slate-900">Event Attendance</h3>
                        <p class="mt-3 text-slate-600">Organize and monitor attendance according to each school event.</p>
                        <div class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-violet-700">Manage Events <span aria-hidden="true">→</span></div>
                    </a>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:px-8">
                <div class="grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="text-[11px] uppercase tracking-[0.24em] text-slate-500">System</div>
                            <div class="mt-3 flex items-center gap-3">
                                <span class="inline-flex h-3 w-3 rounded-full bg-emerald-500 shadow-[0_0_12px_rgba(16,185,129,0.8)]"></span>
                                <span class="text-2xl font-black text-slate-900">Online</span>
                            </div>
                        </div>
                        <div class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="text-[11px] uppercase tracking-[0.24em] text-slate-500">Scanner</div>
                            <div class="mt-3 flex items-center gap-3">
                                <span class="inline-flex h-3 w-3 rounded-full <?php echo $event ? 'bg-emerald-500 shadow-[0_0_12px_rgba(16,185,129,0.8)]' : 'bg-amber-400'; ?>"></span>
                                <span class="text-2xl font-black text-slate-900"><?php echo $event ? 'Ready' : 'Standby'; ?></span>
                            </div>
                        </div>
                        <div class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="text-[11px] uppercase tracking-[0.24em] text-slate-500">Database</div>
                            <div class="mt-3 flex items-center gap-3">
                                <span class="inline-flex h-3 w-3 rounded-full <?php echo $dbConnected ? 'bg-emerald-500 shadow-[0_0_12px_rgba(16,185,129,0.8)]' : 'bg-rose-400'; ?>"></span>
                                <span class="text-2xl font-black text-slate-900"><?php echo $dbConnected ? 'Connected' : 'Unavailable'; ?></span>
                            </div>
                        </div>
                        <div class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="text-[11px] uppercase tracking-[0.24em] text-slate-500">Security</div>
                            <div class="mt-3 flex items-center gap-3">
                                <span class="inline-flex h-3 w-3 rounded-full bg-blue-500 shadow-[0_0_12px_rgba(59,130,246,0.8)]"></span>
                                <span class="text-2xl font-black text-slate-900">Protected</span>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white p-6 shadow-[0_25px_60px_rgba(15,23,42,0.06)]">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <div class="text-xs font-bold uppercase tracking-[0.22em] text-blue-700">Today</div>
                                <h3 class="mt-2 text-3xl font-black tracking-[-0.06em] text-slate-900">Event status</h3>
                            </div>
                            <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-slate-600"><?php echo $event ? 'Active' : 'No Event'; ?></div>
                        </div>

                        <?php if ($event): ?>
                            <div class="mt-6 rounded-[1.6rem] border border-emerald-200 bg-emerald-50 p-5">
                                <div class="flex items-center gap-3 text-emerald-700">
                                    <span class="inline-flex h-3 w-3 rounded-full bg-emerald-500 shadow-[0_0_12px_rgba(16,185,129,0.8)]"></span>
                                    <span class="text-xs font-bold uppercase tracking-[0.24em]">Active Event</span>
                                </div>
                                <h4 class="mt-4 text-3xl font-black tracking-[-0.06em] text-slate-900"><?php echo h($event['event_name']); ?></h4>
                                <p class="mt-3 text-slate-600"><?php echo h($event['venue'] ?? 'School grounds'); ?> · <?php echo h(date('M j, Y', strtotime($event['event_date']))); ?></p>
                                <p class="mt-2 text-sm text-slate-700">Attendance is currently open and ready to monitor.</p>
                                <a href="index1.php" class="mt-5 inline-flex items-center gap-2 rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-emerald-900/20 transition hover:bg-emerald-500">Open Attendance <span aria-hidden="true">→</span></a>
                            </div>
                        <?php else: ?>
                            <div class="mt-6 rounded-[1.6rem] border border-slate-200 bg-slate-50 p-5">
                                <div class="flex items-center gap-3 text-slate-500">
                                    <span class="inline-flex h-3 w-3 rounded-full bg-slate-400"></span>
                                    <span class="text-xs font-bold uppercase tracking-[0.24em]">No active event</span>
                                </div>
                                <p class="mt-4 text-lg font-medium text-slate-700">There is currently no active event.</p>
                                <p class="mt-2 text-slate-600">Check the event schedule for upcoming activities.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:px-8">
                <div class="mb-8">
                    <div class="text-xs font-bold uppercase tracking-[0.22em] text-blue-700">Overview</div>
                    <h2 class="mt-2 text-3xl font-black tracking-[-0.06em] text-slate-900">Attendance overview</h2>
                </div>

                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Registered students</div>
                        <div class="mt-4 text-4xl font-black text-slate-900"><?php echo number_format($studentCount); ?></div>
                    </div>
                    <div class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Today's attendance</div>
                        <div class="mt-4 text-4xl font-black text-slate-900"><?php echo number_format($attendanceToday); ?></div>
                    </div>
                    <div class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Entries</div>
                        <div class="mt-4 text-4xl font-black text-slate-900"><?php echo number_format($entriesToday); ?></div>
                    </div>
                    <div class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Exits</div>
                        <div class="mt-4 text-4xl font-black text-slate-900"><?php echo number_format($exitsToday); ?></div>
                    </div>
                </div>
            </section>

            <section id="features" class="bg-white py-20">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-2xl text-center">
                        <div class="text-xs font-bold uppercase tracking-[0.22em] text-blue-700">How it works</div>
                        <h2 class="mt-2 text-3xl font-black tracking-[-0.06em] text-slate-900">Everything needed for event attendance</h2>
                    </div>

                    <div class="mt-12 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-[1.8rem] border border-slate-200 bg-slate-50 p-6">
                            <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-100 text-blue-700">01</div>
                            <h3 class="text-xl font-bold text-slate-900">Select</h3>
                            <p class="mt-3 text-slate-600">Choose the event or activity that needs monitoring.</p>
                        </div>
                        <div class="rounded-[1.8rem] border border-slate-200 bg-slate-50 p-6">
                            <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">02</div>
                            <h3 class="text-xl font-bold text-slate-900">Scan</h3>
                            <p class="mt-3 text-slate-600">Scan or type the student record for immediate verification.</p>
                        </div>
                        <div class="rounded-[1.8rem] border border-slate-200 bg-slate-50 p-6">
                            <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-100 text-violet-700">03</div>
                            <h3 class="text-xl font-bold text-slate-900">Verify</h3>
                            <p class="mt-3 text-slate-600">Confirm attendance status with a cleaner, secure process.</p>
                        </div>
                        <div class="rounded-[1.8rem] border border-slate-200 bg-slate-50 p-6">
                            <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">04</div>
                            <h3 class="text-xl font-bold text-slate-900">Record</h3>
                            <p class="mt-3 text-slate-600">Save entry and exit activity in a secure, organized log.</p>
                        </div>
                    </div>

                    <div class="mt-16 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                        <div class="rounded-[1.8rem] border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-100 text-sky-700"><svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                            <h3 class="mt-5 text-xl font-bold text-slate-900">Fast attendance</h3>
                            <p class="mt-3 text-slate-600">Quick recording for both arrivals and departures without delays.</p>
                        </div>
                        <div class="rounded-[1.8rem] border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700"><svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2v4M16 2v4M3 10h18M5 5h14a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                            <h3 class="mt-5 text-xl font-bold text-slate-900">Event management</h3>
                            <p class="mt-3 text-slate-600">Stay organized across all school programs, activities, and events.</p>
                        </div>
                        <div class="rounded-[1.8rem] border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-100 text-violet-700"><svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm10 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                            <h3 class="mt-5 text-xl font-bold text-slate-900">Student records</h3>
                            <p class="mt-3 text-slate-600">Organize participation records and event visibility in one place.</p>
                        </div>
                        <div class="rounded-[1.8rem] border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-100 text-indigo-700"><svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                            <h3 class="mt-5 text-xl font-bold text-slate-900">Secure access</h3>
                            <p class="mt-3 text-slate-600">Keep management tools restricted to the right personnel.</p>
                        </div>
                        <div class="rounded-[1.8rem] border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-100 text-amber-700"><svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 17v-1a3 3 0 0 1 6 0v1M4 19h16M6 19V7a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                            <h3 class="mt-5 text-xl font-bold text-slate-900">Attendance logs</h3>
                            <p class="mt-3 text-slate-600">Review event history and participation data when needed.</p>
                        </div>
                        <div class="rounded-[1.8rem] border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-teal-100 text-teal-700"><svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h3l2-7 4 14 2-7h7" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                            <h3 class="mt-5 text-xl font-bold text-slate-900">Real-time status</h3>
                            <p class="mt-3 text-slate-600">Monitor attendance status live as each event proceeds.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-slate-950 py-20 text-white">
                <div class="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:px-8">
                    <div class="flex items-center justify-center lg:translate-y-10">
                        <div class="power-stage flex items-center justify-center rounded-[2rem] border border-white/10 bg-gradient-to-br from-blue-500/10 via-slate-900 to-teal-500/10 shadow-[0_30px_80px_rgba(14,165,233,0.15)]" aria-hidden="true">
                            <div class="absolute inset-6 rounded-[1.5rem] border border-sky-400/30 bg-slate-900/80"></div>
                            <div class="power-smoke"></div><div class="power-smoke"></div><div class="power-smoke"></div><div class="power-beam"></div><div class="power-flash"></div><div class="power-lightning"></div>
                            <div class="power-clash"><div class="clash-beam clash-blue"></div><div class="clash-beam clash-gold"></div><div class="clash-ball clash-ball-blue"></div><div class="clash-ball clash-ball-gold"></div><div class="clash-orb"></div><div class="clash-shockwave"></div></div>
                            <div class="power-ring"></div><div class="power-ring"></div>
                            <div class="power-particle"></div><div class="power-particle"></div><div class="power-particle"></div><div class="power-particle"></div>
                            <div class="power-core"><span>✓</span></div>
                        </div>
                    </div>

                    <div id="about" class="contents">
                        <div class="lg:col-start-2">
                        <div class="text-xs font-bold uppercase tracking-[0.22em] text-sky-300">Security</div>
                        <h2 class="mt-3 text-3xl font-black tracking-[-0.06em] text-white sm:text-4xl">Built around secure attendance</h2>
                        <p class="mt-5 max-w-xl text-lg text-slate-300">Attendance data should be organized, controlled, and accessible only to authorized school personnel.</p>

                        <div class="mt-8 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-slate-200">✓ Authorized access</div>
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-slate-200">✓ Secure records</div>
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-slate-200">✓ Event-based tracking</div>
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-slate-200">✓ Entry and exit monitoring</div>
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-slate-200 sm:col-span-2">✓ Organized logs</div>
                        </div>
                        </div>

                        <div class="col-span-full mt-10 border-t border-white/10 pt-8">
                            <div class="text-xs font-bold uppercase tracking-[0.22em] text-teal-300">Admin build</div>
                            <h3 class="mt-3 text-2xl font-black tracking-[-0.04em] text-white">Built by four kinds of builders</h3>
                            <p class="mt-3 max-w-xl text-slate-400">The people behind the system combine technical thinking, human-centered design, and practical school operations.</p>

                            <div class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                <div class="flex h-full flex-col rounded-2xl border border-sky-300/20 bg-sky-400/10 p-4 text-center">
                                    <div class="relative mb-4 flex h-56 items-center justify-center overflow-hidden bg-transparent sm:h-64">
                                        <img src="Admin%20Pictures/Edjay-transparent.png" alt="Edjay G. Tiana, Hacker and Systems builder" class="relative h-full w-full scale-110 object-contain object-center drop-shadow-[0_18px_18px_rgba(2,132,199,.28)]">
                                        <span class="absolute bottom-3 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-sky-950/70 px-2 py-1 text-[9px] font-bold uppercase tracking-[0.18em] text-sky-200">Code / Core</span>
                                    </div>
                                    <div class="text-lg font-black text-white">Edjay G. Tiana</div>
                                    <div class="text-[10px] font-bold uppercase tracking-[0.18em] text-sky-200">Hacker</div>
                                    <div class="mt-1 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Systems builder</div>
                                    <p class="mt-auto pt-4 text-sm leading-6 text-slate-300">Shapes the secure database, scanner flow, and reliable tools that power every attendance record.</p>
                                </div>

                                <div class="flex h-full flex-col rounded-2xl border border-teal-300/20 bg-teal-400/10 p-4 text-center">
                                    <div class="relative mb-4 flex h-56 items-center justify-center overflow-hidden bg-transparent sm:h-64">
                                        <img src="Admin%20Pictures/Ervin-transparent.png" alt="Ervin Glenn G. Benabese, Hipster and Experience builder" class="relative h-full w-full object-contain object-center drop-shadow-[0_18px_18px_rgba(45,212,191,.28)]">
                                        <span class="absolute bottom-3 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-teal-950/70 px-2 py-1 text-[9px] font-bold uppercase tracking-[0.18em] text-teal-200">Design / Feel</span>
                                    </div>
                                    <div class="text-lg font-black text-white">Ervin Glenn G. Benabese</div>
                                    <div class="mt-1 text-[10px] font-bold uppercase tracking-[0.18em] text-teal-200">Hipster</div>
                                    <div class="mt-1 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Experience builder</div>
                                    <p class="mt-auto pt-4 text-sm leading-6 text-slate-300">Turns complex attendance work into a clear, friendly interface that feels natural on every device.</p>
                                </div>

                                <div class="flex h-full flex-col rounded-2xl border border-amber-300/20 bg-amber-400/10 p-4 text-center">
                                    <div class="relative mb-4 flex h-56 items-center justify-center overflow-hidden bg-transparent sm:h-64">
                                        <img src="Admin%20Pictures/Zurich.png" alt="Zurich John B. Naguita, Action and Drive" class="relative h-full w-full object-contain object-center drop-shadow-[0_18px_18px_rgba(251,191,36,.28)]">
                                        <span class="absolute bottom-3 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-amber-950/70 px-2 py-1 text-[9px] font-bold uppercase tracking-[0.18em] text-amber-200">Action / Drive</span>
                                    </div>
                                    <div class="text-lg font-black text-white">Zurich John B. Naguita</div>
                                    <div class="mt-1 text-[10px] font-bold uppercase tracking-[0.18em] text-amber-200">HUSTLER</div>
                                    <div class="mt-1 text-[10px] font-bold uppercase tracking-[0.18em] text-amber-200">Operations builder</div>
                                    <p class="mt-auto pt-4 text-sm leading-6 text-slate-300">Keeps events moving with practical workflows, quick decisions, and attendance data teams can trust.</p>
                                </div>

                                <div class="flex h-full flex-col rounded-2xl border border-orange-300/20 bg-orange-400/10 p-4 text-center">
                                    <div class="relative mb-4 flex h-56 items-center justify-center overflow-hidden bg-transparent sm:h-64">
                                        <div class="relative flex h-20 w-20 items-center justify-center rounded-2xl border-2 border-orange-200/70 bg-gradient-to-br from-orange-300 to-rose-500 text-slate-950 shadow-[0_0_30px_rgba(251,146,60,.28)]">
                                            <svg viewBox="0 0 24 24" class="h-10 w-10" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 17h14M7 14l3-3 3 2 4-5M17 8h2v2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </div>
                                        <span class="absolute bottom-3 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-orange-950/70 px-2 py-1 text-[9px] font-bold uppercase tracking-[0.18em] text-orange-200">Coordination</span>
                                    </div>
                                    <div class="text-lg font-black text-white">Alexander John F. Jabian</div>
                                    <div class="mt-1 text-[10px] font-bold uppercase tracking-[0.18em] text-orange-200">HUSTLER</div>
                                    <div class="mt-1 text-[10px] font-bold uppercase tracking-[0.18em] text-orange-200">EVENT OPERATIONS</div>
                                    <p class="mt-auto pt-4 text-sm leading-6 text-slate-300">Supports event teams with organized follow-through, clear coordination, and dependable attendance updates.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-[0.22em] text-blue-700">Admin preview</div>
                        <h2 class="mt-2 text-3xl font-black tracking-[-0.06em] text-slate-900">Manage your school attendance in one place</h2>
                    </div>
                    <a href="admin/index.php" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:text-blue-700">Open Dashboard <span aria-hidden="true">→</span></a>
                </div>

                <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-[0_25px_70px_rgba(15,23,42,0.06)] lg:p-8">
                    <div class="grid gap-6 lg:grid-cols-[0.95fr_1.05fr]">
                        <div class="rounded-[1.6rem] border border-slate-200 bg-slate-100 p-6">
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="text-lg font-black text-slate-900">Admin dashboard</h3>
                                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-700">Live</span>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-3">
                                <div class="rounded-2xl bg-white p-3 shadow-sm"><div class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Students</div><div class="mt-2 text-2xl font-black text-slate-900"><?php echo number_format($studentCount); ?></div></div>
                                <div class="rounded-2xl bg-white p-3 shadow-sm"><div class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Events</div><div class="mt-2 text-2xl font-black text-slate-900"><?php echo number_format($eventCount); ?></div></div>
                                <div class="rounded-2xl bg-white p-3 shadow-sm"><div class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Attendance</div><div class="mt-2 text-2xl font-black text-slate-900"><?php echo $attendRate; ?>%</div></div>
                            </div>
                        </div>

                        <div class="rounded-[1.6rem] border border-slate-200 bg-slate-50 p-6">
                            <div class="mb-4 text-lg font-black text-slate-900">Recent attendance</div>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between rounded-2xl bg-white px-4 py-3 shadow-sm">
                                    <div>
                                        <div class="font-semibold text-slate-900">Juan Dela Cruz</div>
                                        <div class="text-xs text-slate-500">Entry · 09:32 AM</div>
                                    </div>
                                    <span class="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-700">✓</span>
                                </div>
                                <div class="flex items-center justify-between rounded-2xl bg-white px-4 py-3 shadow-sm">
                                    <div>
                                        <div class="font-semibold text-slate-900">Maria Santos</div>
                                        <div class="text-xs text-slate-500">Entry · 09:35 AM</div>
                                    </div>
                                    <span class="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-700">✓</span>
                                </div>
                                <div class="flex items-center justify-between rounded-2xl bg-white px-4 py-3 shadow-sm">
                                    <div>
                                        <div class="font-semibold text-slate-900">Mark Reyes</div>
                                        <div class="text-xs text-slate-500">Exit · 10:04 AM</div>
                                    </div>
                                    <span class="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-amber-700">✓</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 pb-24 sm:px-6 lg:px-8">
                <div class="rounded-[2rem] border border-blue-200 bg-gradient-to-r from-blue-50 via-white to-teal-50 p-8 text-center shadow-[0_25px_65px_rgba(37,99,235,0.08)] lg:p-12">
                    <div class="text-xs font-bold uppercase tracking-[0.22em] text-blue-700">Ready to start</div>
                    <h2 class="mt-3 text-3xl font-black tracking-[-0.06em] text-slate-900 sm:text-4xl">Ready to make attendance easier?</h2>
                    <p class="mx-auto mt-4 max-w-2xl text-lg text-slate-600">Open the attendance system and manage student participation in school events with a faster and more organized workflow.</p>
                    <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                        <a href="index1.php" class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-6 py-3 text-sm font-semibold text-white shadow-xl shadow-slate-900/20 transition hover:-translate-y-0.5 hover:bg-blue-700">Open Attendance System <span aria-hidden="true">→</span></a>
                        <a href="admin/events.php" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:text-blue-700">View Event Schedule</a>
                    </div>
                </div>
            </section>
        </main>

        <footer class="relative overflow-hidden bg-slate-950 text-slate-300">
            <div class="pointer-events-none absolute -right-24 -top-32 h-80 w-80 rounded-full bg-blue-500/10 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-40 left-1/3 h-80 w-80 rounded-full bg-teal-400/10 blur-3xl"></div>
            <div class="relative mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                <div class="grid gap-12 lg:grid-cols-[1.3fr_1fr_1fr]">
                    <div>
                        <a href="index.php" class="inline-flex items-center gap-3">
                            <img src="logo/SSC.png" alt="SSC logo" class="h-12 w-12 rounded-2xl object-contain shadow-lg shadow-blue-950/40">
                            <span>
                                <span class="block text-xl font-black tracking-tight text-white">SSCAttendance</span>
                                <span class="block text-[10px] font-bold uppercase tracking-[0.22em] text-slate-500">School attendance system</span>
                            </span>
                        </a>
                        <p class="mt-5 max-w-sm text-sm leading-7 text-slate-400">A clearer way to manage student attendance across school events, activities, competitions, and daily monitoring.</p>
                        <div class="mt-6 inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-2 text-xs font-semibold text-emerald-300">
                            <span class="h-2 w-2 rounded-full bg-emerald-400 shadow-[0_0_10px_rgba(52,211,153,.8)]"></span>
                            System online
                        </div>
                    </div>

                    <div>
                        <div class="text-xs font-bold uppercase tracking-[0.22em] text-sky-300">Explore</div>
                        <div class="mt-5 grid gap-3 text-sm text-slate-400">
                            <a href="index.php" class="w-fit transition hover:text-white">Home</a>
                            <a href="#features" class="w-fit transition hover:text-white">Features</a>
                            <a href="#about" class="w-fit transition hover:text-white">About the system</a>
                            <a href="admin/events.php" class="w-fit transition hover:text-white">Event schedule</a>
                        </div>
                    </div>

                    <div>
                        <div class="text-xs font-bold uppercase tracking-[0.22em] text-teal-300">Workspace</div>
                        <div class="mt-5 grid gap-3 text-sm text-slate-400">
                            <a href="index1.php" class="w-fit transition hover:text-white">Attendance scanner</a>
                            <a href="scan.php" class="w-fit transition hover:text-white">Student entry / exit</a>
                            <a href="admin/students.php" class="w-fit transition hover:text-white">Student records</a>
                            <a href="admin/index.php" class="w-fit transition hover:text-white">Admin dashboard</a>
                        </div>
                    </div>
                </div>

                <div class="mt-12 flex flex-col gap-4 border-t border-white/10 pt-6 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                    <div>© <?php echo date('Y'); ?> SSCAttendance. Built for better school events.</div>
                    <div class="flex items-center gap-3"><span class="h-1 w-1 rounded-full bg-slate-600"></span><span>Secure records</span><span class="h-1 w-1 rounded-full bg-slate-600"></span><span>Live monitoring</span></div>
                </div>
            </div>
        </footer>
    </div>

    <?php if (!empty($announcements)): ?>
    <div class="fixed inset-x-0 bottom-0 z-40 px-0 pb-0">
        <div class="news-broadcast">
            <div class="news-brand">
                <div class="news-brand-inner">
                    <div class="news-brand-line"><span>SCHOOL</span><span class="accent">NEWS</span></div>
                    <div class="news-brand-sub">Announcement</div>
                </div>
            </div>
            <div class="news-ticker-wrap">
                <div class="news-ticker-track">
                    <?php foreach (array_merge($announcements, $announcements) as $announcement): ?>
                        <span class="news-ticker-item">
                            <span class="news-ticker-tag"><?php echo h($announcement['announcement_type'] ?: 'Announcement'); ?></span>
                            <span class="news-ticker-title"><?php echo h($announcement['title']); ?></span>
                            <span class="news-ticker-divider">|</span>
                            <span class="news-ticker-message"><?php echo h($announcement['message']); ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        const menuButton = document.getElementById('menuButton');
        const mobileMenu = document.getElementById('mobileMenu');
        if (menuButton && mobileMenu) {
            mobileMenu.querySelectorAll('a').forEach(function (link) {
                link.addEventListener('click', function () {
                    mobileMenu.classList.add('hidden');
                    menuButton.setAttribute('aria-expanded', 'false');
                });
            });
        }
    </script>
</body>
</html>
