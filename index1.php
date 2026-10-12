<?php
define('APP_SKIP_SESSION_START', true);
require_once __DIR__.'/config.php';
$conn = db();
$homepage = $conn->query("WITH settings AS (
    SELECT
        COALESCE(MAX(setting_value) FILTER (WHERE setting_key='organization_name'), 'Student Entry and Exit Using a Barcode and SMS Integration System') AS organization_name,
        COALESCE(MAX(setting_value) FILTER (WHERE setting_key='overnight_events'), '0') AS overnight_events,
        COALESCE(MAX(setting_value) FILTER (WHERE setting_key='ticker_speed'), '30') AS ticker_speed
    FROM ssc_settings
), active_event AS (
    SELECT e.*
    FROM events e
    CROSS JOIN settings s
    WHERE e.status='ACTIVE' AND e.attendance_required=1
      AND (
          e.event_date=CURRENT_DATE
          OR (s.overnight_events='1' AND e.event_date=CURRENT_DATE - INTERVAL '1 day' AND e.end_time < e.start_time)
      )
    ORDER BY e.event_date DESC, e.start_time
    LIMIT 1
), incoming_event AS (
    SELECT event_name,event_date
    FROM events
    WHERE event_date>CURRENT_DATE AND status IN ('UPCOMING','DRAFT')
    ORDER BY event_date ASC,start_time ASC
    LIMIT 1
), today_event AS (
    SELECT event_name,status
    FROM events
    WHERE event_date=CURRENT_DATE AND status IN ('ACTIVE','UPCOMING','DRAFT')
    ORDER BY CASE status WHEN 'ACTIVE' THEN 1 WHEN 'UPCOMING' THEN 2 ELSE 3 END,start_time
    LIMIT 1
)
SELECT
    s.organization_name,
    s.ticker_speed,
    (SELECT to_jsonb(e)::text FROM active_event e) AS active_event,
    COALESCE((SELECT COUNT(*) FROM event_students es JOIN active_event e ON e.id=es.event_id),0) AS registered,
    (SELECT to_jsonb(e)::text FROM incoming_event e) AS incoming_event,
    (SELECT to_jsonb(e)::text FROM today_event e) AS today_event,
    COALESCE((
        SELECT json_agg(to_jsonb(a) ORDER BY a.priority DESC,a.sort_order ASC,a.created_at DESC)::text
        FROM school_announcements a
        WHERE a.status='ACTIVE' AND (a.starts_at IS NULL OR a.starts_at<=CURRENT_TIMESTAMP)
          AND (a.ends_at IS NULL OR a.ends_at>=CURRENT_TIMESTAMP)
    ), '[]') AS announcements
FROM settings s")->fetch_assoc();
$organizationName = $homepage['organization_name'];
$event = json_decode((string)($homepage['active_event'] ?? 'null'), true, 512, JSON_THROW_ON_ERROR);
$registered = (int)$homepage['registered'];
$incomingEvent = json_decode((string)($homepage['incoming_event'] ?? 'null'), true, 512, JSON_THROW_ON_ERROR);
$todayEvent = json_decode((string)($homepage['today_event'] ?? 'null'), true, 512, JSON_THROW_ON_ERROR);
$announcements = json_decode((string)$homepage['announcements'], true, 512, JSON_THROW_ON_ERROR);
$tickerSpeed = max(10, min(90, (int)$homepage['ticker_speed']));
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?php echo h($organizationName); ?></title>
<link rel="icon" type="image/png" href="/logo/SSC.png">
<link rel="apple-touch-icon" href="/logo/SSC.png">
<script src="https://cdn.tailwindcss.com"></script>
<script src="assets/app.js?v=20261011-progress" defer></script>
<style>
  .aurora{position:absolute;filter:blur(60px);opacity:.55}.a1{width:60vw;height:60vw;left:-15vw;top:-15vh;background:radial-gradient(closest-side,rgba(99,102,241,.5),transparent 60%)}.a2{width:50vw;height:50vw;right:-10vw;top:-10vh;background:radial-gradient(closest-side,rgba(16,185,129,.55),transparent 60%)}.a3{width:45vw;height:45vw;left:10vw;bottom:-15vh;background:radial-gradient(closest-side,rgba(245,158,11,.5),transparent 60%)}.glass{background-color:rgba(255,255,255,.64);backdrop-filter:saturate(150%) blur(18px)}.hero-modal{box-shadow:0 30px 80px rgba(15,23,42,.2),0 8px 24px rgba(79,70,229,.12),inset 0 1px rgba(255,255,255,.95)}.lift{transition:transform .25s ease,box-shadow .25s ease}.lift:hover{transform:translateY(-5px);box-shadow:0 18px 32px rgba(15,23,42,.14)}.news-broadcast{display:flex;align-items:stretch;width:calc(100% - 1rem);max-width:1500px;margin:0 auto;border:1px solid rgba(15,23,42,.08);border-radius:1rem 1rem 0 0;overflow:hidden;background:rgba(248,250,252,.95);box-shadow:0 -12px 30px rgba(15,23,42,.08)}.news-brand{display:flex;align-items:center;justify-content:center;flex-shrink:0;width:150px;padding:.5rem .8rem;background:linear-gradient(180deg,#f8fafc 0%,#e2e8f0 100%);border-right:1px solid rgba(15,23,42,.08)}.news-brand-inner{display:flex;flex-direction:column;line-height:.9;text-transform:uppercase}.news-brand-line{font-size:1.2rem;font-weight:900;letter-spacing:-.06em;color:#0f172a}.news-brand-line .accent{color:#dc2626}.news-brand-sub{font-size:.52rem;letter-spacing:.18em;font-weight:800;color:#475569;margin-top:.22rem}.news-ticker-wrap{position:relative;flex:1;overflow:hidden;background:linear-gradient(90deg,#ef4444 0%,#b91c1c 100%)}.news-ticker-track{display:inline-flex;align-items:center;white-space:nowrap;min-width:max-content;padding-left:.5rem;will-change:transform;animation:tickerMove <?php echo (int)$tickerSpeed; ?>s linear infinite}.news-ticker-track:hover{animation-play-state:paused}.news-ticker-item{display:inline-flex;align-items:center;gap:.7rem;padding:.65rem .75rem;color:#fff}.news-ticker-tag{display:inline-flex;align-items:center;border-radius:999px;background:rgba(255,255,255,.16);padding:.2rem .5rem;font-size:.58rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.news-ticker-title{font-size:.88rem;font-weight:800}.news-ticker-divider{opacity:.75}.news-ticker-message{font-size:.8rem;font-weight:700;opacity:.97}.news-ticker-message strong{font-weight:900}@keyframes tickerMove{from{transform:translateX(0)}to{transform:translateX(-50%)}}a[href="admin/index.php"]>div:first-child>div:last-child{margin-top:.75rem}a[href="scan.php"]>div:last-child{flex-direction:row-reverse}@media(max-width:640px){.hero-modal{border-radius:1.25rem;padding:1.25rem}.hero-modal h1{font-size:2rem;line-height:1.1;overflow-wrap:anywhere}.hero-modal p{line-height:1.5}.hero-modal .grid{gap:.75rem}.hero-modal a{min-height:8.5rem;padding:1rem}.hero-modal a>div:first-child>div{overflow-wrap:anywhere}.hero-modal .text-xl{font-size:1.05rem}.hero-modal .text-2xl{font-size:1.35rem}.news-broadcast{width:calc(100% - .5rem);border-radius:.8rem .8rem 0 0}.news-brand{width:150px;padding:.5rem}.news-brand-line{font-size:1.15rem}.news-brand-sub{font-size:.4rem;letter-spacing:.18em}.news-ticker-item{gap:.5rem;padding:.6rem .7rem}.news-ticker-title{font-size:.68rem}.news-ticker-message{font-size:.66rem}.news-ticker-tag{font-size:.45rem;letter-spacing:.12em;padding:.2rem .4rem}}
</style>
<style>
@media(max-width:640px){
  body{padding-top:env(safe-area-inset-top);padding-bottom:env(safe-area-inset-bottom)}
  .hero-modal{width:100%;border-radius:1.25rem!important;padding:1.25rem!important}
  .hero-modal h1{font-size:clamp(1.8rem,8.5vw,2.5rem);overflow-wrap:anywhere}
  .hero-modal>.mt-7.grid{margin-top:1.25rem!important}
  .hero-modal>.mt-7.grid{display:grid!important;grid-template-columns:minmax(0,1fr)!important;gap:.75rem!important;width:100%;}
  .hero-modal>.mt-7.grid>a{min-height:8.5rem;padding:1rem;display:flex;flex-direction:column;justify-content:space-between;gap:.75rem}
  .hero-modal>.mt-7.grid>a>div:first-child{min-width:0;display:flex;align-items:center;gap:.5rem}
  .hero-modal>.mt-7.grid>a>div:first-child>div:first-child{min-width:0}
  .hero-modal>.mt-7.grid>a .text-lg,.hero-modal>.mt-7.grid>a .text-xl{min-width:0;overflow-wrap:anywhere;white-space:normal;font-size:1rem;line-height:1.25}
  .hero-modal>.mt-7.grid>a>div:first-child>div:last-child{display:flex;flex-shrink:0;margin-top:0;gap:.45rem}
  .hero-modal>.mt-7.grid>a[href="admin/index.php"]>div:first-child>div:last-child{width:100%;justify-content:center;text-align:center;white-space:normal;flex-shrink:1}
  .hero-modal>.mt-7.grid>a>div:last-child{min-width:0;margin-top:0;display:flex;align-items:center;justify-content:space-between;gap:.5rem}
  .hero-modal>.mt-7.grid>a>div:last-child>span:first-child{min-width:0;overflow-wrap:anywhere;white-space:normal;font-size:.75rem}
  .hero-modal>.mt-6.grid{grid-template-columns:minmax(0,1fr)!important}
  .fixed.right-5.top-5{top:calc(.75rem + env(safe-area-inset-top));right:calc(.75rem + env(safe-area-inset-right));}
  .fixed.right-5.top-5 a{padding:.55rem .75rem;font-size:.72rem}
}
</style>
<style>
html,body{min-height:100%;overflow-x:hidden}body{min-height:100dvh}
@media(max-width:640px){main{padding-bottom:5.5rem!important}.news-broadcast{width:calc(100% - 1rem);border-radius:.75rem .75rem 0 0}.news-brand{width:92px;padding:.4rem}.news-brand-line{font-size:.9rem}.news-brand-sub{font-size:.42rem}.news-ticker-item{gap:.45rem;padding:.65rem .75rem}.news-ticker-title{font-size:.75rem}}
</style>
<style>
  .hero-modal .text-orange-500{color:#dc2626!important}
  #cameraLaunch{background-image:url('logo/Camera.png');background-position:center;background-repeat:no-repeat;background-size:2rem}
  #cameraLaunch svg{display:none}
  #cameraLaunch{filter:none!important;mix-blend-mode:normal!important}
</style>
<link rel="stylesheet" href="assets/responsive.css?v=20261012-school-news">
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 opacity-0 translate-y-1">
<div class="fixed right-5 top-5 z-50">
  <a href="index.php" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white/90 px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-sky-200 hover:text-sky-700">
    <span>←</span>
    <span>Back to Webpage</span>
  </a>
</div>
<div class="relative min-h-screen overflow-hidden"><div class="aurora a1"></div><div class="aurora a2"></div><div class="aurora a3"></div><div class="relative flex min-h-screen items-center"><main class="attendance-home-main mx-auto w-full max-w-5xl p-3 sm:p-6"><section class="hero-modal glass rounded-[2rem] border border-white/80 p-5 text-center sm:p-8 md:p-10"><div class="inline-flex items-center gap-2 rounded-full bg-indigo-100 px-3 py-1 text-xs font-medium text-indigo-700 sm:text-sm">PHP + MySQL · Tailwind</div><h1 class="mx-auto mt-4 max-w-3xl text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl md:text-5xl"><?php echo h($organizationName); ?></h1><p class="mx-auto mt-3 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">Fast, smooth, and secure attendance scanning for authorized school events and activities.</p><div id="clock" class="mt-2 text-xs text-slate-500"></div><div class="mx-auto mt-7 grid max-w-4xl gap-4 md:grid-cols-2">
<a href="scan.php" class="lift group relative flex min-h-36 flex-col justify-between rounded-2xl border border-emerald-200 bg-white/70 p-4 text-left shadow-sm hover:bg-white sm:p-5 <?php echo !$event?'pointer-events-none opacity-60':''; ?>"><div><div class="flex flex-nowrap items-center justify-center gap-2 text-emerald-700 sm:justify-start"><svg width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M12 12a5 5 0 1 0-5-5a5 5 0 0 0 5 5m-7 8a7 7 0 0 0 14 0z"/></svg><div class="shrink-0 whitespace-nowrap text-lg font-semibold sm:text-xl">Student Entry / Exit</div></div><div class="mt-3 flex justify-center gap-4 whitespace-nowrap text-xs font-bold tracking-[.18em]"><span class="inline-flex items-center gap-1 text-emerald-600"><svg width="14" height="14" viewBox="0 0 24 24"><path fill="currentColor" d="M10 17v-6H6l6 6h-4v6z"/></svg>ENTRY</span><span class="inline-flex items-center gap-1 text-orange-500"><svg width="14" height="14" viewBox="0 0 24 24"><path fill="currentColor" d="M14 7v6h4l-6 6l-6-6h4V7z"/></svg>EXIT</span></div></div><div class="mt-2 flex items-center justify-between gap-3 text-sm text-slate-500"><span class="whitespace-nowrap">Ready for scanner input →</span><span id="cameraLaunch" role="button" tabindex="0" title="Open camera scanner" aria-label="Open camera scanner" class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700 shadow-sm transition hover:bg-emerald-100"><svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M9 4l1.5-2h3L15 4h3a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3zm3 4a5 5 0 1 0 0 10a5 5 0 0 0 0-5.6m0 2.2a2.8 2.8 0 1 1 0 5.6a2.8 2.8 0 0 1 0-5.6"/></svg></span></div></a>
<a href="admin/index.php" class="lift group flex min-h-36 flex-col justify-between rounded-2xl border border-violet-200 bg-white/70 p-4 text-left shadow-sm transition hover:bg-white hover:shadow-xl sm:p-5"><div><div class="flex flex-nowrap items-center justify-center gap-2 text-violet-700 sm:justify-start"><svg width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M12 12a5 5 0 1 0-5-5a5 5 0 0 0 5 5m-7 8a7 7 0 0 1 14 0z"/></svg><div class="shrink-0 whitespace-nowrap text-xl font-semibold">Admin Dashboard</div></div><div class="whitespace-nowrap text-center text-slate-500">Manage students and logs</div></div><div class="mt-2 self-end whitespace-nowrap text-sm text-slate-500 transition group-hover:translate-x-0.5">Open dashboard →</div></a>
</div><div class="mx-auto mt-6 grid max-w-4xl gap-3 text-left sm:grid-cols-2 lg:grid-cols-4"><div class="rounded-xl border border-white/70 bg-white/60 p-4"><div class="text-xs uppercase tracking-widest text-slate-500">Event status</div><div class="mt-1 font-semibold <?php echo $event?'text-emerald-700':'text-slate-600'; ?>"><?php echo $event?'ACTIVE':'NO ACTIVE EVENT'; ?></div></div><div class="rounded-xl border border-white/70 bg-white/60 p-4"><div class="text-xs uppercase tracking-widest text-slate-500">Registered students</div><div class="mt-1 text-2xl font-bold"><?php echo number_format($registered); ?></div></div><div class="rounded-xl border border-white/70 bg-white/60 p-4"><div class="text-xs uppercase tracking-widest text-slate-500">Event today</div><?php if ($todayEvent): ?><div class="mt-1 font-semibold text-indigo-700"><?php echo h($todayEvent['event_name']); ?></div><?php else: ?><div class="mt-1 font-semibold text-slate-500">No event today</div><?php endif; ?></div><div class="rounded-xl border border-white/70 bg-white/60 p-4"><div class="text-xs uppercase tracking-widest text-slate-500">Incoming events</div><?php if ($incomingEvent): ?><div class="mt-1 font-semibold text-violet-700"><?php echo h($incomingEvent['event_name']); ?></div><div class="mt-1 text-xs text-slate-500"><?php echo h($incomingEvent['event_date']); ?></div><?php else: ?><div class="mt-1 font-semibold text-slate-500">No incoming event</div><?php endif; ?></div></div></section></main></div></div>
<?php if (!empty($announcements)): ?>
<div class="fixed inset-x-0 bottom-0 z-40 school-news-dock bg-transparent text-slate-50">
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
            <span class="news-ticker-tag"><?php echo h($announcement['announcement_type'] ?: 'ANNOUNCEMENT'); ?></span>
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
<script>const clock=document.getElementById('clock');function tick(){clock.textContent=new Date().toLocaleString('en-PH',{dateStyle:'medium',timeStyle:'medium'})}tick();setInterval(tick,1000);const scanCard=document.querySelector('a[href="scan.php"]'),scanTitle=scanCard&&scanCard.querySelector('.text-lg');if(scanTitle)scanTitle.textContent='Student Entry / Exit →';const readyText=document.querySelector('a[href="scan.php"]>div:last-child>span:first-child');if(readyText)readyText.textContent='Ready for scanner input →';const cameraLaunch=document.getElementById('cameraLaunch');if(cameraLaunch){const openCamera=event=>{event.preventDefault();event.stopPropagation();window.location.href='scan.php?camera=1'};cameraLaunch.addEventListener('click',openCamera);cameraLaunch.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key===' '){openCamera(event)}})}window.addEventListener('load',()=>document.body.classList.remove('opacity-0','translate-y-1'));</script>
</body><script>const entryIcon=document.querySelector('a[href="scan.php"] .text-emerald-600 svg path');const exitIcon=document.querySelector('a[href="scan.php"] .text-orange-500 svg path');if(entryIcon){entryIcon.setAttribute('d','M12 21V7.8l-4.6 4.6L6 11l6-6l6 6l-1.4 1.4L13 7.8V21z');entryIcon.setAttribute('fill','none');entryIcon.setAttribute('stroke','currentColor');entryIcon.setAttribute('stroke-width','2.6');entryIcon.setAttribute('stroke-linecap','round');entryIcon.setAttribute('stroke-linejoin','round')}if(exitIcon){exitIcon.setAttribute('fill','none');exitIcon.setAttribute('stroke','currentColor');exitIcon.setAttribute('stroke-width','2.6');exitIcon.setAttribute('stroke-linecap','round');exitIcon.setAttribute('stroke-linejoin','round')}</script></html>