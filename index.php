<?php
declare(strict_types=1);

const DEFAULT_TIMEZONE = 'Europe/Amsterdam';

/**
 * Stop with a plain-text error and an HTTP 400 status.
 */
function fail(string $message): void
{
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Error: ' . $message;
    exit;
}

/**
 * Return the first query parameter that is set, from a list of aliases.
 * The Dutch names are kept as fallback so existing embed URLs keep working.
 */
function getParam(array $names): ?string
{
    foreach ($names as $name) {
        if (isset($_GET[$name]) && is_string($_GET[$name])) {
            return trim($_GET[$name]);
        }
    }

    return null;
}

/**
 * Read an integer query parameter, or return the default when it is missing.
 * Returns null when the value is present but not a whole number.
 */
function getIntParam(array $names, int $default): ?int
{
    $value = getParam($names);

    if ($value === null || $value === '') {
        return $default;
    }

    if (!preg_match('/^-?\d+$/', $value)) {
        return null;
    }

    return (int) $value;
}

function getCssColorParam(array $names, string $default): string
{
    $value = getParam($names);

    if ($value === null || $value === '') {
        return $default;
    }

    if (strtolower($value) === 'transparent') {
        return 'transparent';
    }

    // Allow hex colors without #:
    // 123      => #123
    // 1234     => #1234
    // 123456   => #123456
    // 12345678 => #12345678
    if (preg_match('/^([0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value)) {
        return '#' . $value;
    }

    return $default;
}

if (getParam(['day', 'dag']) === null || getParam(['month', 'maand']) === null) {
    fail('Missing required parameters: day and month.');
}

$timezoneName = getParam(['tz', 'timezone']) ?: DEFAULT_TIMEZONE;

try {
    $timezone = new DateTimeZone($timezoneName);
} catch (Exception $e) {
    fail('Invalid timezone.');
}

$now = new DateTime('now', $timezone);
$currentYear = (int) $now->format('Y');

$yearGiven = getParam(['year', 'jaar']) !== null && getParam(['year', 'jaar']) !== '';

$day = getIntParam(['day', 'dag'], 0);
$month = getIntParam(['month', 'maand'], 0);
$year = getIntParam(['year', 'jaar'], $currentYear);
$hour = getIntParam(['hour', 'uur'], 10);
$minute = getIntParam(['minute', 'minuut'], 0);
// Hours the countdown stays at zero after the target has passed, before it
// moves on to next year's date. Only used when no year is given.
$holdHours = getIntParam(['hold'], 24);

if (
    $day === null || $month === null || $year === null || $hour === null || $minute === null ||
    $holdHours === null ||
    $day < 1 || $day > 31 ||
    $month < 1 || $month > 12 ||
    $year < 1970 || $year > 9999 ||
    $hour < 0 || $hour > 23 ||
    $minute < 0 || $minute > 59 ||
    $holdHours < 0 || $holdHours > 8760
) {
    fail('Invalid date or time parameters.');
}

/**
 * Build the target moment for a given year, or null when that date does not
 * exist in that year (e.g. 29 February in a non-leap year).
 */
function buildTarget(int $year, int $month, int $day, int $hour, int $minute, DateTimeZone $timezone): ?DateTime
{
    $dateString = sprintf('%04d-%02d-%02d %02d:%02d:00', $year, $month, $day, $hour, $minute);
    $target = DateTime::createFromFormat('!Y-m-d H:i:s', $dateString, $timezone);

    if (
        !$target ||
        (int) $target->format('n') !== $month ||
        (int) $target->format('j') !== $day ||
        (int) $target->format('Y') !== $year
    ) {
        return null;
    }

    return $target;
}

$targetDateTime = null;

if ($yearGiven) {
    $targetDateTime = buildTarget($year, $month, $day, $hour, $minute, $timezone);
} else {
    // No year given: use the next occurrence of this date. A target that has
    // just passed is kept for the hold period, so the countdown shows zeros
    // for a while instead of jumping straight to ~365 days.
    // Starting one year back covers e.g. a 31 December target viewed on 1 January.
    $holdSeconds = $holdHours * 3600;

    for ($candidateYear = $currentYear - 1; $candidateYear <= $currentYear + 8; $candidateYear++) {
        $candidate = buildTarget($candidateYear, $month, $day, $hour, $minute, $timezone);

        if ($candidate && $candidate->getTimestamp() + $holdSeconds > $now->getTimestamp()) {
            $targetDateTime = $candidate;
            break;
        }
    }
}

if (!$targetDateTime) {
    fail('Invalid calendar date.');
}

// Unix timestamp in milliseconds, so every visitor counts down to the same moment
// regardless of the timezone of their own device.
$targetTimestamp = $targetDateTime->getTimestamp() * 1000;

$pageBackground = getCssColorParam(['bg', 'background'], 'transparent');
$boxBackground = getCssColorParam(['boxbg', 'boxbackground'], '#000000dd');
$textColor = getCssColorParam(['text', 'textcolor', 'numbercolor'], '#eeeeee');
$labelColor = getCssColorParam(['label', 'labelcolor'], '#ffffff99');

// Optional message shown instead of the boxes once the countdown has ended.
const MESSAGE_MAX_LENGTH = 100;
$message = (string) getParam(['message', 'done']);
$message = preg_replace('/\s+/u', ' ', $message) ?? '';
$message = mb_substr(trim($message), 0, MESSAGE_MAX_LENGTH, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Countdown</title>

    <style>
        :root {
            --page-bg: <?php echo htmlspecialchars($pageBackground, ENT_QUOTES, 'UTF-8'); ?>;
            --box-bg: <?php echo htmlspecialchars($boxBackground, ENT_QUOTES, 'UTF-8'); ?>;
            --text-color: <?php echo htmlspecialchars($textColor, ENT_QUOTES, 'UTF-8'); ?>;
            --label-color: <?php echo htmlspecialchars($labelColor, ENT_QUOTES, 'UTF-8'); ?>;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            width: 100%;
            background: var(--page-bg) !important;
            background-color: var(--page-bg) !important;
            overflow: hidden;
        }

        body {
            font-family: system-ui, sans-serif;
        }

        .countdown {
            width: 100%;
            max-width: 720px;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: clamp(0.25rem, 1.5vw, 0.75rem);
            padding: 0;
            margin: 0 auto;
            background: transparent !important;
        }

        .countdown-box {
            min-width: 0;
            padding: clamp(0.45rem, 2.2vw, 0.9rem);
            text-align: center;
            border: 1px solid rgba(0, 0, 0, 0.35);
            border-radius: clamp(8px, 2vw, 14px);
            background: var(--box-bg);
        }

        .countdown-box span {
            display: block;
            color: var(--text-color);
            font-size: clamp(1.25rem, 7vw, 3rem);
            font-weight: 800;
            line-height: 1;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .countdown-box small {
            display: block;
            margin-top: clamp(0.2rem, 1vw, 0.35rem);
            color: var(--label-color);
            font-size: clamp(0.45rem, 1.8vw, 0.7rem);
            letter-spacing: clamp(0.02em, 0.4vw, 0.1em);
            text-transform: uppercase;
            white-space: nowrap;
        }

        .countdown-message {
            max-width: 720px;
            margin: 0 auto;
            padding: clamp(0.7rem, 3vw, 1.3rem) clamp(0.6rem, 3vw, 1.2rem);
            text-align: center;
            border: 1px solid rgba(0, 0, 0, 0.35);
            border-radius: clamp(8px, 2vw, 14px);
            background: var(--box-bg);
            color: var(--text-color);
            font-size: clamp(1.1rem, 5.5vw, 2.4rem);
            font-weight: 800;
            line-height: 1.15;
            overflow-wrap: anywhere;
        }

        [hidden] {
            display: none !important;
        }

        /* Only the number pulses, so the box (and its border) never grows past the page edges. */
        .tick span {
            animation: tickPulse 0.45s ease;
        }

        @keyframes tickPulse {
            0% {
                transform: scale(1);
            }

            45% {
                transform: scale(1.04);
            }

            100% {
                transform: scale(1);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .tick span {
                animation: none;
            }
        }

        @media (max-width: 520px) {
            .countdown {
                gap: 0.3rem;
            }

            .countdown-box {
                padding: 0.45rem 0.25rem;
                border-radius: 10px;
            }

            .countdown-box span {
                font-size: clamp(1.05rem, 7vw, 1.8rem);
            }

            .countdown-box small {
                font-size: clamp(0.4rem, 1.9vw, 0.52rem);
                letter-spacing: 0.02em;
            }
        }

        @media (max-width: 360px) {
            .countdown {
                gap: 0.2rem;
            }

            .countdown-box {
                padding: 0.35rem 0.2rem;
                border-radius: 8px;
            }

            .countdown-box span {
                font-size: clamp(0.95rem, 6.8vw, 1.35rem);
            }

            .countdown-box small {
                font-size: 0.38rem;
                letter-spacing: 0;
            }
        }
    </style>
</head>
<body>

    <div class="countdown" id="countdown" role="timer" aria-live="off">
        <div class="countdown-box">
            <span id="days">00</span>
            <small>Days</small>
        </div>

        <div class="countdown-box">
            <span id="hours">00</span>
            <small>Hours</small>
        </div>

        <div class="countdown-box">
            <span id="minutes">00</span>
            <small>Minutes</small>
        </div>

        <div class="countdown-box" id="secondsBox">
            <span id="seconds">00</span>
            <small>Seconds</small>
        </div>
    </div>

    <div class="countdown-message" id="message" role="status" hidden><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>

    <script>
        const targetDate = <?php echo json_encode($targetTimestamp); ?>;

        const daysEl = document.getElementById("days");
        const hoursEl = document.getElementById("hours");
        const minutesEl = document.getElementById("minutes");
        const secondsEl = document.getElementById("seconds");
        const secondsBox = document.getElementById("secondsBox");
        const countdownEl = document.getElementById("countdown");
        const messageEl = document.getElementById("message");

        const SECOND = 1000;
        const MINUTE = SECOND * 60;
        const HOUR = MINUTE * 60;
        const DAY = HOUR * 24;

        function pad(value) {
            return String(value).padStart(2, "0");
        }

        function render(days, hours, minutes, seconds) {
            daysEl.textContent = pad(days);
            hoursEl.textContent = pad(hours);
            minutesEl.textContent = pad(minutes);
            secondsEl.textContent = pad(seconds);
        }

        // Swap the boxes for the message, shrinking the text when needed so it
        // fits in the same height and the embedding iframe does not change size.
        function showMessage() {
            const maxHeight = countdownEl.offsetHeight;

            countdownEl.hidden = true;
            messageEl.hidden = false;

            let fontSize = parseFloat(getComputedStyle(messageEl).fontSize);

            while (maxHeight > 0 && messageEl.offsetHeight > maxHeight && fontSize > 10) {
                fontSize -= 1;
                messageEl.style.fontSize = fontSize + "px";
            }
        }

        function updateCountdown() {
            const distance = targetDate - Date.now();

            if (distance <= 0) {
                render(0, 0, 0, 0);

                // Show the custom message, if one was given, instead of the zeros.
                if (messageEl.textContent !== "" && messageEl.hidden) {
                    showMessage();
                }

                return;
            }

            // Round up to whole seconds so the display reaches 00 exactly at the target time.
            const remaining = Math.ceil(distance / SECOND) * SECOND;

            render(
                Math.floor(remaining / DAY),
                Math.floor((remaining % DAY) / HOUR),
                Math.floor((remaining % HOUR) / MINUTE),
                Math.floor((remaining % MINUTE) / SECOND)
            );

            secondsBox.classList.remove("tick");
            void secondsBox.offsetWidth;
            secondsBox.classList.add("tick");

            // Schedule the next update on the next whole second to avoid drift.
            setTimeout(updateCountdown, (distance % SECOND) || SECOND);
        }

        updateCountdown();
    </script>

</body>
</html>
