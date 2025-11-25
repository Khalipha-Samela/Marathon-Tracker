<?php
// ----------------------------
// CONFIGURATION
// ----------------------------
define("MARATHON_DISTANCE", 42.195);
$historyFile = "race_history.json";

// ----------------------------
// HELPER FUNCTIONS
// ----------------------------
function formatTime($minutes) {
    if ($minutes <= 0) return "0m";
    $h = floor($minutes / 60);
    $m = $minutes % 60;
    return $h . "h " . $m . "m";
}

function formatSpeed($speed) {
    if ($speed <= 0) return "0 km/h";
    return number_format($speed, 2) . " km/h";
}

function loadHistory($file) {
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function saveHistory($file, $entry) {
    $history = loadHistory($file);
    $history[] = $entry;
    file_put_contents($file, json_encode($history, JSON_PRETTY_PRINT));
}

// ----------------------------
// PROCESS CLEAR HISTORY REQUEST
// ----------------------------
if (isset($_POST['clear_history'])) {
    file_put_contents($historyFile, json_encode([])); // clear JSON
    header("Location: " . $_SERVER['PHP_SELF']); // refresh page
    exit;
}

// ----------------------------
// PROCESS FORM SUBMISSION
// ----------------------------
$results = null;
$historicalData = loadHistory($historyFile);

if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['clear_history'])) {
    $coveredDistance = floatval($_POST['covered_distance']);
    $elapsedHours = intval($_POST['elapsed_hours']);
    $elapsedMinutes = intval($_POST['elapsed_minutes']);
    $targetHours = intval($_POST['target_hours']);
    $targetMinutes = intval($_POST['target_minutes']);

    // Convert times → minutes
    $elapsedTime = ($elapsedHours * 60) + $elapsedMinutes;
    $targetTime = ($targetHours * 60) + $targetMinutes;

    // Sanity protection
    if ($coveredDistance < 0) $coveredDistance = 0;
    if ($coveredDistance > MARATHON_DISTANCE) $coveredDistance = MARATHON_DISTANCE;

    if ($elapsedTime <= 0 || $targetTime <= 0 || $elapsedTime >= $targetTime) {
        $error = "Invalid time values.";
    } else {
        // Speed calculations
        $currentSpeed = $coveredDistance > 0 ? ($coveredDistance / ($elapsedTime / 60)) : 0;

        $remainingDistance = MARATHON_DISTANCE - $coveredDistance;
        $remainingTime = $targetTime - $elapsedTime;
        $requiredSpeed = $remainingDistance > 0 ? ($remainingDistance / ($remainingTime / 60)) : 0;

        $results = [
            "coveredDistance" => $coveredDistance,
            "elapsedTime" => $elapsedTime,
            "targetTime" => $targetTime,
            "currentSpeed" => $currentSpeed,
            "requiredSpeed" => $requiredSpeed
        ];

        // Save entry to history
        saveHistory($historyFile, [
            "date" => date("Y-m-d H:i"),
            "covered_distance" => $coveredDistance,
            "elapsed_time" => $elapsedTime,
            "current_speed" => $currentSpeed,
            "required_speed" => $requiredSpeed
        ]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marathon Runner Progress Tracker</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Marathon Runner Progress Tracker</h1>
            <p class="subtitle">Track your progress and calculate the speed needed to achieve your target time</p>
        </header>

        <div class="content">
            <div class="input-section">
                <h2 class="section-title">Enter Your Progress</h2>

                <?php if (!empty($error)): ?>
                    <p class="error"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>

                <form method="post" autocomplete="off">
                    <div class="form-group">
                        <label for="covered_distance">Distance Covered (km)</label>
                        <input type="number" id="covered_distance" name="covered_distance"
                            min="0" max="50" step="0.1" required
                            placeholder="0-50 km" autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label>Elapsed Time</label>
                        <div class="time-inputs">
                            <input type="number" name="elapsed_hours" placeholder="Hours"
                                   min="0" max="24" required autocomplete="off">
                            <input type="number" name="elapsed_minutes" placeholder="Minutes"
                                   min="0" max="59" required autocomplete="off">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Target Finish Time</label>
                        <div class="time-inputs">
                            <input type="number" name="target_hours" placeholder="Hours"
                                   min="0" max="24" required autocomplete="off">
                            <input type="number" name="target_minutes" placeholder="Minutes"
                                   min="0" max="59" required autocomplete="off">
                        </div>
                    </div>

                    <button type="submit">Calculate Required Speed</button>
                </form>

                <div class="progress-bar">
                    <div class="progress"
                        style="width: <?= $results ? ($results['coveredDistance'] / MARATHON_DISTANCE) * 100 : 0 ?>%;">
                        <?= $results ?
                            round(($results['coveredDistance'] / MARATHON_DISTANCE) * 100, 1) . '%' :
                            '0%' ?>
                    </div>
                </div>

                <div class="examples">
                    <h3>Example Inputs:</h3>
                    <ul>
                        <li>25 km, 2h 30m elapsed, 4h 15m target</li>
                        <li>30 km, 2h 45m elapsed, 4h 30m target</li>
                        <li>15 km, 1h 45m elapsed, 4h 00m target</li>
                    </ul>
                </div>
            </div>

            <div class="results-section">
                <h2 class="section-title">Your Results</h2>

                <?php if ($results): ?>
                    <div class="result-box">
                        <div class="result-title">Current Average Speed</div>
                        <div class="result-value"><?= formatSpeed($results['currentSpeed']) ?></div>
                    </div>

                    <div class="result-box">
                        <div class="result-title">Required Speed to Finish on Time</div>
                        <div class="result-value"><?= formatSpeed($results['requiredSpeed']) ?></div>
                    </div>

                    <div class="result-box">
                        <div class="result-title">Remaining Distance</div>
                        <div class="result-value">
                            <?= number_format(MARATHON_DISTANCE - $results['coveredDistance'], 2) ?> km
                        </div>
                    </div>

                    <div class="result-box">
                        <div class="result-title">Time Remaining</div>
                        <div class="result-value">
                            <?= formatTime($results['targetTime'] - $results['elapsedTime']) ?>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="result-box">
                        <p>Enter progress to calculate performance.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="history-section">
                <h2 class="section-title">Your Historical Race Data</h2>

                <!-- CLEAR HISTORY BUTTON -->
                <form method="post" style="margin-bottom: 15px;">
                    <button type="submit" name="clear_history" class="delete-btn">
                        Clear History
                    </button>
                </form>

                <?php if (!empty($historicalData)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Distance (km)</th>
                                <th>Elapsed</th>
                                <th>Speed</th>
                                <th>Required</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_reverse($historicalData) as $entry): ?>
                                <tr>
                                    <td><?= htmlspecialchars($entry['date']) ?></td>
                                    <td><?= number_format($entry['covered_distance'], 2) ?></td>
                                    <td><?= formatTime($entry['elapsed_time']) ?></td>
                                    <td><?= formatSpeed($entry['current_speed']) ?></td>
                                    <td><?= formatSpeed($entry['required_speed']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No history yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

<script>
    // Reset form after load (after calculate)
    window.addEventListener('load', () => {
        const form = document.querySelector('form');
        if (form) form.reset();
    });
</script>

</body>
</html>
