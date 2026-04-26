<?php
$summary = $summary ?? [
    'progress_percent' => 0,
    'course_completed' => false,
    'required_lessons' => 0,
    'required_completed_lessons' => 0,
];
$selectedLesson = $selectedLesson ?? null;
$progressPercent = (int) ($summary['progress_percent'] ?? 0);
$courseCompleted = !empty($summary['course_completed']);
?>
<section class="student-course-player-shell space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="student-course-player-title text-2xl font-semibold"><?= e((string) ($course['name'] ?? 'Curso')); ?></h2>
            <p class="student-course-player-subtitle text-sm text-slate-500">Trilha por modulos com bloqueio de ordem e regra minima de 70% por aula em video.</p>
        </div>
        <a href="<?= route('student/courses'); ?>" class="student-course-player-back rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Voltar para Meus Cursos</a>
    </div>

    <div class="student-course-player-progress rounded-xl border border-slate-200 bg-white p-4">
        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
            <p class="student-course-player-progress-label font-medium text-slate-700">Progresso do curso: <span id="course-progress-label"><?= $progressPercent; ?>%</span></p>
            <p class="student-course-player-progress-meta text-slate-500">Aulas obrigatorias concluidas: <?= (int) ($summary['required_completed_lessons'] ?? 0); ?>/<?= (int) ($summary['required_lessons'] ?? 0); ?></p>
        </div>
        <div class="student-course-player-progress-track mt-2 h-2 rounded-full bg-slate-200">
            <div id="course-progress-bar" class="h-2 rounded-full <?= $courseCompleted ? 'bg-emerald-600' : 'bg-cyan-600'; ?>" style="width: <?= $progressPercent; ?>%"></div>
        </div>
        <p class="student-course-player-progress-note mt-2 text-xs <?= $courseCompleted ? 'text-emerald-700' : 'text-slate-500'; ?>">
            <?= $courseCompleted ? 'Curso concluido.' : 'Cada aula exige progresso minimo (padrao 70%) para liberar o proximo modulo.'; ?>
        </p>
    </div>

    <div class="student-course-player-layout grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
        <aside class="student-course-player-modules space-y-3">
            <?php foreach ($modules as $module): ?>
                <?php
                $moduleUnlocked = !empty($module['is_unlocked']);
                $moduleCompleted = !empty($module['is_completed']);
                ?>
                <article class="student-module-card rounded-xl border <?= $moduleUnlocked ? 'student-module-card-open border-slate-200 bg-white' : 'student-module-card-locked border-slate-200 bg-slate-50'; ?> p-3">
                    <div class="mb-2 flex items-start justify-between gap-2">
                        <div>
                            <p class="student-module-title text-sm font-semibold text-slate-900"><?= e((string) $module['title']); ?></p>
                            <?php if (trim((string) ($module['description'] ?? '')) !== ''): ?>
                                <p class="student-module-description text-xs text-slate-500"><?= e((string) $module['description']); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if (!$moduleUnlocked): ?>
                            <span class="student-module-badge student-module-badge-locked rounded-full px-2 py-1 text-[11px] font-semibold">Bloqueado</span>
                        <?php elseif ($moduleCompleted): ?>
                            <span class="student-module-badge student-module-badge-complete rounded-full px-2 py-1 text-[11px] font-semibold">Concluido</span>
                        <?php else: ?>
                            <span class="student-module-badge student-module-badge-active rounded-full px-2 py-1 text-[11px] font-semibold">Em andamento</span>
                        <?php endif; ?>
                    </div>

                    <div class="space-y-2">
                        <?php foreach (($module['lessons'] ?? []) as $lesson): ?>
                            <?php
                            $lessonSelected = $selectedLesson && (int) ($selectedLesson['id'] ?? 0) === (int) ($lesson['id'] ?? 0);
                            $lessonCompleted = !empty($lesson['is_completed']);
                            ?>
                            <?php if ($moduleUnlocked): ?>
                                <a href="<?= route('student/course&course_id=' . (int) ($course['course_id'] ?? 0) . '&lesson_id=' . (int) $lesson['id']); ?>" class="student-lesson-card block rounded-lg border px-3 py-2 text-sm <?= $lessonSelected ? 'student-lesson-card-selected border-cyan-300 bg-cyan-50' : 'student-lesson-card-default border-slate-200 hover:bg-slate-50'; ?>">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="student-lesson-title font-medium text-slate-800"><?= e((string) $lesson['title']); ?></p>
                                        <span class="student-lesson-progress text-xs <?= $lessonCompleted ? 'text-emerald-700' : 'text-slate-500'; ?>">
                                            <?= (int) ($lesson['progress_percent'] ?? 0); ?>%
                                        </span>
                                    </div>
                                    <p class="student-lesson-meta mt-1 text-[11px] text-slate-500">Minimo: <?= (int) ($lesson['min_progress_percent'] ?? 70); ?>%<?= !empty($lesson['is_required']) ? ' | obrigatoria' : ' | opcional'; ?></p>
                                </a>
                            <?php else: ?>
                                <div class="student-lesson-card-locked rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-500">
                                    <p class="font-medium student-lesson-title"><?= e((string) $lesson['title']); ?></p>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <?php if (($module['lessons'] ?? []) === []): ?>
                            <p class="student-module-empty rounded-lg border border-dashed border-slate-200 px-3 py-2 text-xs text-slate-500">Sem aulas neste modulo.</p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>

            <?php if ($modules === []): ?>
                <article class="student-module-no-data rounded-xl border border-dashed border-slate-300 bg-white p-4 text-sm text-slate-500">
                    Este curso ainda nao possui modulos/aulas cadastrados.
                </article>
            <?php endif; ?>
        </aside>

        <section class="student-course-player-stage rounded-xl border border-slate-200 bg-white p-4">
            <?php if ($selectedLesson): ?>
                <div class="space-y-3">
                    <div>
                        <h3 class="student-stage-title text-xl font-semibold text-slate-900"><?= e((string) $selectedLesson['title']); ?></h3>
                        <?php if (trim((string) ($selectedLesson['description'] ?? '')) !== ''): ?>
                            <p class="student-stage-description text-sm text-slate-500"><?= e((string) $selectedLesson['description']); ?></p>
                        <?php endif; ?>
                    </div>

                    <?php
                    $videoUrl   = (string) ($selectedLesson['video_url'] ?? '');
                    $youtubeId  = null;
                    if (preg_match('/(?:youtube\.com\/watch\?[^#]*v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/shorts\/)([a-zA-Z0-9_-]{11})/', $videoUrl, $_ytm)) {
                        $youtubeId = $_ytm[1];
                    }
                    $lessonDataAttrs = implode(' ', [
                        'data-course-id="'         . (int) ($course['course_id'] ?? 0) . '"',
                        'data-lesson-id="'         . (int) ($selectedLesson['id'] ?? 0) . '"',
                        'data-required-percent="'  . (int) ($selectedLesson['min_progress_percent'] ?? 70) . '"',
                        'data-initial-watched="'   . (int) ($selectedLesson['watched_seconds'] ?? 0) . '"',
                        'data-initial-position="'  . (int) ($selectedLesson['last_position_seconds'] ?? 0) . '"',
                        'data-initial-progress="'  . (int) ($selectedLesson['progress_percent'] ?? 0) . '"',
                        'data-initial-completed="' . (!empty($selectedLesson['is_completed']) ? '1' : '0') . '"',
                    ]);
                    ?>
                    <?php if ($youtubeId): ?>
                        <div
                            id="yt-player-wrap"
                            class="relative w-full overflow-hidden rounded-xl bg-black"
                            style="padding-top:56.25%"
                            data-youtube-id="<?= e($youtubeId); ?>"
                            <?= $lessonDataAttrs; ?>
                        >
                            <div id="yt-player" style="position:absolute;top:0;left:0;width:100%;height:100%;"></div>
                        </div>
                    <?php else: ?>
                        <video
                            id="lesson-video"
                            controls
                            preload="metadata"
                            class="w-full rounded-xl bg-black"
                            src="<?= e($videoUrl); ?>"
                            <?= $lessonDataAttrs; ?>
                        ></video>
                    <?php endif; ?>

                    <div class="student-stage-progress rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                            <p class="font-medium text-slate-700">Progresso da aula: <span id="lesson-progress-label"><?= (int) ($selectedLesson['progress_percent'] ?? 0); ?>%</span></p>
                            <p id="lesson-status-label" class="text-xs <?= !empty($selectedLesson['is_completed']) ? 'text-emerald-700' : 'text-slate-500'; ?>">
                                <?= !empty($selectedLesson['is_completed']) ? 'Aula concluida.' : 'Assista no minimo ' . (int) ($selectedLesson['min_progress_percent'] ?? 70) . '% para concluir.'; ?>
                            </p>
                        </div>
                        <div class="mt-2 h-2 rounded-full bg-slate-200">
                            <div id="lesson-progress-bar" class="h-2 rounded-full <?= !empty($selectedLesson['is_completed']) ? 'bg-emerald-600' : 'bg-cyan-600'; ?>" style="width: <?= (int) ($selectedLesson['progress_percent'] ?? 0); ?>%"></div>
                        </div>
                    </div>

                    <p class="student-stage-help text-xs text-slate-500">
                        Suporte a links do YouTube (youtube.com/watch ou youtu.be) e arquivos diretos (MP4/WebM).
                    </p>
                </div>
            <?php else: ?>
                <div class="student-stage-empty rounded-lg border border-dashed border-slate-300 bg-slate-50 p-6 text-sm text-slate-500">
                    Nenhuma aula disponivel para abrir agora. Finalize os modulos anteriores para liberar os proximos.
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>

<?php if ($selectedLesson): ?>
<script>
(function () {
    const player = document.getElementById('lesson-video');
    if (!player) {
        return;
    }

    const endpoint = '<?= route('student/course/progress'); ?>';
    const csrfToken = '<?= csrf_token(); ?>';
    const lessonProgressLabel = document.getElementById('lesson-progress-label');
    const lessonStatusLabel = document.getElementById('lesson-status-label');
    const lessonProgressBar = document.getElementById('lesson-progress-bar');
    const courseProgressLabel = document.getElementById('course-progress-label');
    const courseProgressBar = document.getElementById('course-progress-bar');

    const courseId = Number(player.dataset.courseId || '0');
    const lessonId = Number(player.dataset.lessonId || '0');
    const requiredPercent = Number(player.dataset.requiredPercent || '70');

    if (courseId <= 0 || lessonId <= 0) {
        return;
    }

    const state = {
        watchedSeconds: Number(player.dataset.initialWatched || '0'),
        lastTime: 0,
        lastSyncedAt: 0,
        syncInFlight: false,
        completed: player.dataset.initialCompleted === '1',
    };

    const toPositiveInt = (value, fallback = 1) => {
        const parsed = Number(value);
        if (!Number.isFinite(parsed) || parsed <= 0) {
            return fallback;
        }
        return Math.round(parsed);
    };

    const currentDuration = () => {
        const duration = Number(player.duration || 0);
        if (Number.isFinite(duration) && duration > 0) {
            return toPositiveInt(duration, 1);
        }
        return toPositiveInt(player.dataset.initialPosition || 0, 1);
    };

    const refreshLessonUi = (progress, completed) => {
        const safeProgress = Math.max(0, Math.min(100, Math.round(progress)));
        if (lessonProgressLabel) {
            lessonProgressLabel.textContent = safeProgress + '%';
        }
        if (lessonProgressBar) {
            lessonProgressBar.style.width = safeProgress + '%';
            lessonProgressBar.classList.toggle('bg-emerald-600', completed);
            lessonProgressBar.classList.toggle('bg-cyan-600', !completed);
        }
        if (lessonStatusLabel) {
            lessonStatusLabel.textContent = completed
                ? 'Aula concluida.'
                : 'Assista no minimo ' + requiredPercent + '% para concluir.';
            lessonStatusLabel.classList.toggle('text-emerald-700', completed);
            lessonStatusLabel.classList.toggle('text-slate-500', !completed);
        }
    };

    const refreshCourseUi = (progress) => {
        const safeProgress = Math.max(0, Math.min(100, Math.round(progress)));
        if (courseProgressLabel) {
            courseProgressLabel.textContent = safeProgress + '%';
        }
        if (courseProgressBar) {
            courseProgressBar.style.width = safeProgress + '%';
        }
    };

    const syncProgress = async (force = false) => {
        if (state.syncInFlight) {
            return;
        }

        const nowTs = Date.now();
        if (!force && nowTs - state.lastSyncedAt < 9000) {
            return;
        }

        const duration = currentDuration();
        const position = Math.round(Number(player.currentTime || 0));
        const watched = Math.max(position, Math.round(state.watchedSeconds));

        const payload = new URLSearchParams();
        payload.append('_csrf', csrfToken);
        payload.append('course_id', String(courseId));
        payload.append('lesson_id', String(lessonId));
        payload.append('watched_seconds', String(watched));
        payload.append('duration_seconds', String(duration));
        payload.append('position_seconds', String(position));

        state.syncInFlight = true;

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: payload.toString(),
                keepalive: force,
            });

            const data = await response.json();
            if (!response.ok || !data || !data.ok) {
                return;
            }

            const lessonProgress = Number(data.progress_percent || 0);
            const lessonCompleted = !!data.lesson_completed;
            refreshLessonUi(lessonProgress, lessonCompleted);

            const courseProgress = Number(data.course_progress_percent || 0);
            refreshCourseUi(courseProgress);

            if (!state.completed && data.lesson_just_completed) {
                state.completed = true;
                window.setTimeout(() => {
                    window.location.reload();
                }, 900);
            }
        } catch (error) {
            // Ignore transient network errors while the user is watching.
        } finally {
            state.syncInFlight = false;
            state.lastSyncedAt = Date.now();
        }
    };

    player.addEventListener('loadedmetadata', () => {
        const initialPosition = Number(player.dataset.initialPosition || '0');
        if (Number.isFinite(initialPosition) && initialPosition > 0 && initialPosition < Number(player.duration || 0)) {
            player.currentTime = initialPosition;
        }
        state.lastTime = Number(player.currentTime || 0);
    });

    player.addEventListener('seeking', () => {
        state.lastTime = Number(player.currentTime || 0);
    });

    player.addEventListener('timeupdate', () => {
        const current = Number(player.currentTime || 0);
        const delta = current - state.lastTime;

        if (delta > 0 && delta <= 2.5) {
            state.watchedSeconds += delta;
        }

        state.lastTime = current;
        syncProgress(false);
    });

    player.addEventListener('pause', () => {
        syncProgress(true);
    });

    player.addEventListener('ended', () => {
        syncProgress(true);
    });

    window.addEventListener('beforeunload', () => {
        syncProgress(true);
    });
})();
</script>
<?php endif; ?>

<?php if ($selectedLesson && $youtubeId): ?>
<script>
(function () {
    const wrap = document.getElementById('yt-player-wrap');
    if (!wrap) { return; }

    const endpoint      = '<?= route('student/course/progress'); ?>';
    const csrfToken     = '<?= csrf_token(); ?>';
    const courseId      = Number(wrap.dataset.courseId || '0');
    const lessonId      = Number(wrap.dataset.lessonId || '0');
    const requiredPct   = Number(wrap.dataset.requiredPercent || '70');
    const youtubeId     = wrap.dataset.youtubeId || '';

    const lessonProgressLabel = document.getElementById('lesson-progress-label');
    const lessonStatusLabel   = document.getElementById('lesson-status-label');
    const lessonProgressBar   = document.getElementById('lesson-progress-bar');
    const courseProgressLabel = document.getElementById('course-progress-label');
    const courseProgressBar   = document.getElementById('course-progress-bar');

    if (!courseId || !lessonId || !youtubeId) { return; }

    const state = {
        watchedSeconds: Number(wrap.dataset.initialWatched || '0'),
        lastTime: 0,
        lastSyncedAt: 0,
        syncInFlight: false,
        completed: wrap.dataset.initialCompleted === '1',
        ticker: null,
    };

    const refreshLessonUi = (progress, completed) => {
        const p = Math.max(0, Math.min(100, Math.round(progress)));
        if (lessonProgressLabel) { lessonProgressLabel.textContent = p + '%'; }
        if (lessonProgressBar) {
            lessonProgressBar.style.width = p + '%';
            lessonProgressBar.classList.toggle('bg-emerald-600', completed);
            lessonProgressBar.classList.toggle('bg-cyan-600', !completed);
        }
        if (lessonStatusLabel) {
            lessonStatusLabel.textContent = completed
                ? 'Aula concluida.'
                : 'Assista no minimo ' + requiredPct + '% para concluir.';
            lessonStatusLabel.classList.toggle('text-emerald-700', completed);
            lessonStatusLabel.classList.toggle('text-slate-500', !completed);
        }
    };

    const refreshCourseUi = (progress) => {
        const p = Math.max(0, Math.min(100, Math.round(progress)));
        if (courseProgressLabel) { courseProgressLabel.textContent = p + '%'; }
        if (courseProgressBar)   { courseProgressBar.style.width = p + '%'; }
    };

    const syncProgress = async (force = false) => {
        if (state.syncInFlight) { return; }
        const now = Date.now();
        if (!force && now - state.lastSyncedAt < 9000) { return; }

        let duration = 1;
        let position = 0;
        try {
            duration = Math.max(1, Math.round(window._ytPlayerInstance.getDuration() || 1));
            position = Math.round(window._ytPlayerInstance.getCurrentTime() || 0);
        } catch (_) {}

        const watched = Math.max(position, Math.round(state.watchedSeconds));

        const payload = new URLSearchParams();
        payload.append('_csrf', csrfToken);
        payload.append('course_id', String(courseId));
        payload.append('lesson_id', String(lessonId));
        payload.append('watched_seconds', String(watched));
        payload.append('duration_seconds', String(duration));
        payload.append('position_seconds', String(position));

        state.syncInFlight = true;
        try {
            const res  = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: payload.toString(),
                keepalive: force,
            });
            const data = await res.json();
            if (!res.ok || !data || !data.ok) { return; }

            refreshLessonUi(Number(data.progress_percent || 0), !!data.lesson_completed);
            refreshCourseUi(Number(data.course_progress_percent || 0));

            if (!state.completed && data.lesson_just_completed) {
                state.completed = true;
                window.setTimeout(() => window.location.reload(), 900);
            }
        } catch (_) {
        } finally {
            state.syncInFlight  = false;
            state.lastSyncedAt  = Date.now();
        }
    };

    // Carrega a YouTube IFrame API de forma assíncrona.
    window.onYouTubeIframeAPIReady = function () {
        window._ytPlayerInstance = new YT.Player('yt-player', {
            videoId: youtubeId,
            playerVars: { rel: 0, modestbranding: 1 },
            events: {
                onReady: function (e) {
                    const initialPos = Number(wrap.dataset.initialPosition || '0');
                    if (initialPos > 0) {
                        try { e.target.seekTo(initialPos, true); } catch (_) {}
                    }
                },
                onStateChange: function (e) {
                    if (e.data === YT.PlayerState.PLAYING) {
                        state.lastTime = window._ytPlayerInstance.getCurrentTime();
                        state.ticker = setInterval(() => {
                            try {
                                const current = window._ytPlayerInstance.getCurrentTime();
                                const delta   = current - state.lastTime;
                                if (delta > 0 && delta <= 3) { state.watchedSeconds += delta; }
                                state.lastTime = current;
                            } catch (_) {}
                            syncProgress(false);
                        }, 2000);
                    } else {
                        clearInterval(state.ticker);
                        state.ticker = null;
                        if (e.data === YT.PlayerState.PAUSED || e.data === YT.PlayerState.ENDED) {
                            syncProgress(true);
                        }
                    }
                },
            },
        });
    };

    window.addEventListener('beforeunload', () => syncProgress(true));

    const tag = document.createElement('script');
    tag.src   = 'https://www.youtube.com/iframe_api';
    document.head.appendChild(tag);
})();
</script>
<?php endif; ?>

