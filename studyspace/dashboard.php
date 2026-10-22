
<?php
session_start();

// Protect the dashboard from unauthenticated access.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>StudySpace | Dashboard</title>
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <a class="brand" href="#">
        <span class="brand-mark">S</span>
        <span>studyspace<span class="brand-dot">.</span></span>
      </a>

      <div class="nav-label">WORKSPACE</div>
      <nav class="main-nav" aria-label="Main navigation">
        <a class="nav-link active" href="#dashboard"><span class="nav-icon">▦</span>Dashboard</a>
        <a class="nav-link" href="#tasks"><span class="nav-icon">☷</span>My tasks <span class="nav-count">8</span></a>
        <a class="nav-link" href="#calendar"><span class="nav-icon">▦</span>Calendar</a>
        <a class="nav-link" href="#resources"><span class="nav-icon">▤</span>Resources</a>
      </nav>

      <div class="nav-label second-label">PERSONAL</div>
      <nav class="main-nav" aria-label="Personal navigation">
        <a class="nav-link" href="#saved"><span class="nav-icon">♡</span>Saved resources</a>
        <a class="nav-link" href="#settings"><span class="nav-icon">⚙</span>Settings</a>
      </nav>

      <div class="sidebar-bottom">
        <div class="focus-card">
          <div class="focus-symbol">✦</div>
          <p class="focus-title">Small steps add up.</p>
          <p class="focus-copy">Keep showing up for your goals, one task at a time.</p>
          <div class="focus-progress"><span></span></div>
          <div class="focus-foot"><span>Weekly progress</span><strong>65%</strong></div>
        </div>
        <a class="profile-mini" href="#profile">
          <div class="avatar">DW</div>
          <div class="profile-copy"><strong>Your profile</strong><span>Student account</span></div>
          <span class="more">···</span>
        </a>
      </div>
    </aside>

    <main class="main-content" id="dashboard">
      <header class="topbar">
        <div class="breadcrumb">Workspace <span>/</span> <strong>Dashboard</strong></div>
        <div class="topbar-actions">
          <div class="date-chip"><span>◷</span> Tuesday, 13 October</div>
          <button class="icon-button" aria-label="Notifications">♧<i></i></button>
          <div class="avatar top-avatar">DW</div>
        </div>
      </header>

      <section class="welcome-row">
        <div>
          <p class="eyebrow">YOUR PERSONAL STUDY SPACE</p>
          <h1>Good morning, Dewashwi <span class="wave">✳</span></h1>
          <p class="welcome-subtitle">A new day, a fresh page. Here's what's on your mind today.</p>
        </div>
        <button class="primary-button"><span>＋</span> Add a task</button>
      </section>

      <section class="stats-grid" aria-label="Task overview">
        <article class="stat-card">
          <div class="stat-top"><span class="stat-icon blue-icon">☷</span><span class="stat-trend">This week</span></div>
          <p class="stat-label">Total tasks</p>
          <div class="stat-value">12 <span class="stat-detail">assignments</span></div>
          <div class="mini-bars" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
        </article>
        <article class="stat-card">
          <div class="stat-top"><span class="stat-icon green-icon">✓</span><span class="stat-trend positive">↑ 2 this week</span></div>
          <p class="stat-label">Completed</p>
          <div class="stat-value">04 <span class="stat-detail">of 12 tasks</span></div>
          <div class="progress-line"><span class="progress-green"></span></div>
          <p class="tiny-note">You're making steady progress.</p>
        </article>
        <article class="stat-card">
          <div class="stat-top"><span class="stat-icon amber-icon">◷</span><span class="stat-trend">Keep an eye on these</span></div>
          <p class="stat-label">Due soon</p>
          <div class="stat-value">03 <span class="stat-detail">in the next 7 days</span></div>
          <div class="due-dots"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
        </article>
      </section>

      <section class="content-grid">
        <div class="panel tasks-panel" id="tasks">
          <div class="panel-heading">
            <div><h2>Upcoming assignments</h2><p>Keep the important things in sight.</p></div>
            <a class="text-link" href="#all-tasks">View all <span>→</span></a>
          </div>
          <div class="task-tabs"><a class="tab active" href="#all">All tasks <span>8</span></a><a class="tab" href="#pending">In progress</a><a class="tab" href="#completed">Completed</a></div>

          <div class="task-list">
            <article class="task-row">
              <span class="task-check"></span>
              <div class="task-main"><div class="task-title-line"><h3>Software Engineering report</h3><span class="priority high">High</span></div><p>CIS · Coursework</p><div class="task-meta"><span class="meta-icon">▣</span> Tomorrow, 11:59 PM <span class="task-tag blue-tag">Writing</span></div></div>
              <button class="row-more" aria-label="More task options">···</button>
            </article>
            <article class="task-row">
              <span class="task-check"></span>
              <div class="task-main"><div class="task-title-line"><h3>Review database lecture notes</h3><span class="priority medium">Medium</span></div><p>Database Systems · Revision</p><div class="task-meta"><span class="meta-icon">▣</span> Thursday, 15 October <span class="task-tag purple-tag">Revision</span></div></div>
              <button class="row-more" aria-label="More task options">···</button>
            </article>
            <article class="task-row">
              <span class="task-check"></span>
              <div class="task-main"><div class="task-title-line"><h3>Prepare project proposal</h3><span class="priority high">High</span></div><p>Final Year Project · Planning</p><div class="task-meta"><span class="meta-icon">▣</span> Friday, 16 October <span class="task-tag green-tag">Project</span></div></div>
              <button class="row-more" aria-label="More task options">···</button>
            </article>
            <article class="task-row">
              <span class="task-check"></span>
              <div class="task-main"><div class="task-title-line"><h3>Read chapter 4 — Web APIs</h3><span class="priority low">Low</span></div><p>Web Development · Reading</p><div class="task-meta"><span class="meta-icon">▣</span> Sunday, 18 October <span class="task-tag amber-tag">Reading</span></div></div>
              <button class="row-more" aria-label="More task options">···</button>
            </article>
          </div>
          <a class="bottom-link" href="#all-tasks">See all assignments <span>→</span></a>
        </div>

        <div class="right-column">
          <section class="panel calendar-panel" id="calendar">
            <div class="panel-heading compact-heading"><div><h2>Your week</h2><p>13 – 19 October 2026</p></div><button class="calendar-arrow" aria-label="Next week">→</button></div>
            <div class="week-days"><div><span>TU</span><b class="today">13</b></div><div><span>WE</span><b>14</b></div><div><span>TH</span><b>15</b></div><div><span>FR</span><b>16</b></div><div><span>SA</span><b>17</b></div><div><span>SU</span><b>18</b></div><div><span>MO</span><b>19</b></div></div>
            <div class="calendar-divider"></div>
            <div class="calendar-event"><span class="event-line blue-line"></span><div><strong>Software Engineering report</strong><span>Due tomorrow · 11:59 PM</span></div></div>
            <div class="calendar-event"><span class="event-line purple-line"></span><div><strong>Database revision</strong><span>Thursday · Personal study</span></div></div>
            <div class="calendar-event"><span class="event-line green-line"></span><div><strong>Project proposal</strong><span>Friday · Submission</span></div></div>
            <a class="bottom-link" href="#calendar-view">Open calendar <span>→</span></a>
          </section>

          <section class="resource-card" id="resources">
            <div class="resource-orbit orbit-one"></div><div class="resource-orbit orbit-two"></div>
            <div class="resource-content"><span class="resource-kicker">A LITTLE INSPIRATION</span><h2>Curiosity looks<br>good on you.</h2><p>Discover books and learning resources for whatever you're exploring.</p><a href="#search-resources">Explore resources <span>↗</span></a></div>
            <div class="book-stack" aria-hidden="true"><span></span><span></span><span></span></div>
          </section>
        </div>
      </section>
      <footer class="footer"><span>Made for your next chapter.</span><span>StudySpace <b>·</b> Stay curious.</span></footer>
    </main>
  </div>
</body>
</html>
