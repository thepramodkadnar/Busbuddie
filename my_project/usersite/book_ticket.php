<?php
// Start a new session or resume the existing session
session_start();

// Include database connection file
include 'config.php';

// ✅ Check if user is logged in; if not, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Redirect to login page
    exit(); // Stop further execution
}

// ✅ Check if bus_id is provided in the URL
if (!isset($_GET['bus_id'])) {
    echo "Invalid request. No bus selected."; // No bus was chosen
    exit();
}

// Sanitize and store bus_id from URL
$bus_id = intval($_GET['bus_id']); // Ensure it is an integer
$user_id = $_SESSION['user_id']; // Get logged-in user's ID
$message = ""; // Message for errors or confirmations

// ✅ Fetch bus details from database
$busQuery = $conn->prepare("SELECT * FROM buses WHERE id = ?");
$busQuery->bind_param("i", $bus_id); // Bind bus_id as integer
$busQuery->execute();
$bus = $busQuery->get_result()->fetch_assoc(); // Fetch bus data as array

// If no bus found, show error and stop
if (!$bus) {
    echo "Bus not found.";
    exit();
}

// ✅ Fetch already booked seats for this bus
$bookedSeats = []; // Store all booked seat numbers here
$bookedQuery = $conn->prepare("SELECT seat_number FROM bookings WHERE bus_id = ? AND status = 'booked'");
$bookedQuery->bind_param("i", $bus_id);
$bookedQuery->execute();
$result = $bookedQuery->get_result();

// Loop through all booked seats and add them to array
while ($row = $result->fetch_assoc()) {
    // Each booking can have multiple seats separated by commas
    $bookedSeats = array_merge($bookedSeats, explode(",", $row['seat_number']));
}

// ✅ Handle booking form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Get seat number(s) and booking date from form
    $seat_number = $_POST['seat_number'] ?? ''; // If not set, default empty
    $booking_date = $_POST['booking_date'] ?? '';
    $fare = $_POST['fare'] ?? 0;

    // If seat or date is missing, show error
    if (empty($seat_number) || empty($booking_date)) {
        $message = "Please select seats and date.";
    } else {
        // Insert booking into database with status = 'pending_payment'
        $stmt = $conn->prepare("INSERT INTO bookings (user_id, source, destination, fare, bus_id, seat_number, booking_date, status) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_payment')");
        $stmt->bind_param(
            "issdiss", // Data types: int, string, string, double, int, string, string
            $user_id,
            $bus['source'],
            $bus['destination'],
            $fare,
            $bus_id,
            $seat_number,
            $booking_date
        );

        // Execute query
        if ($stmt->execute()) {
            $booking_id = $stmt->insert_id; // Get last inserted booking ID
            // Redirect to QR payment page
            header("Location: qr_payment.php?booking_id=" . $booking_id);
            exit();
        } else {
            $message = "Error: " . $stmt->error; // Show error if insert fails
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Book Ticket</title>
    <style>
        /* Basic page styling */
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        /* Main content container */
        .container {
            max-width: 800px;
            margin: 40px auto;
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        /* Page heading */
        h2 {
            text-align: center;
            color: #d32f2f;
        }
        /* Bus information section */
        .bus-info {
            text-align: center;
            margin-bottom: 20px;
        }
        .bus-info p {
            margin: 5px 0;
            font-size: 16px;
        }
        /* Seat layout grid */
        .seat-layout {
            display: grid;
            grid-template-columns: repeat(4, 50px); /* 4 seats per row */
            gap: 10px;
            justify-content: center;
            margin: 20px 0;
        }
        /* Seat styling */
        .seat {
            width: 50px;
            height: 50px;
            background: #ccc;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s;
        }
        /* Selected seat styling */
        .seat.selected {
            background: #4CAF50;
            color: white;
        }
        /* Already booked seat styling */
        .seat.booked {
            background: #e53935;
            color: white;
            cursor: not-allowed;
        }
        /* Fare display */
        .total-fare {
            text-align: center;
            font-weight: bold;
            margin-top: 10px;
            font-size: 18px;
        }
        /* Date input & button */
        input[type="date"], button {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            font-size: 16px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        /* Book button */
        button {
            background: #d32f2f;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background: #b71c1c;
        }
        /* Success/Error message styling */
        .message {
            text-align: center;
            color: green;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Book Your Ticket</h2>
    <?php if ($message) echo "<p class='message'>$message</p>"; ?>

    <!-- Bus details -->
    <div class="bus-info">
        <p><b>Bus:</b> <?php echo htmlspecialchars($bus['bus_name']); ?></p>
        <p><b>Route:</b> <?php echo htmlspecialchars($bus['source']); ?> → <?php echo htmlspecialchars($bus['destination']); ?></p>
        <p><b>Fare per seat:</b> ₹<?php echo htmlspecialchars($bus['price']); ?></p>
    </div>

    <!-- Booking form -->
    <form method="POST">
        <!-- Seat layout display -->
        <div class="seat-layout" id="seatLayout">
            <?php
            $totalSeats = $bus['total_seats']; // Get total seats from DB
            for ($i = 1; $i <= $totalSeats; $i++):
                $seatNo = "S$i"; // Example seat numbers: S1, S2, S3
                // Mark booked seats in red
                $class = in_array($seatNo, $bookedSeats) ? "seat booked" : "seat";
                echo "<div class='$class' data-seat='$seatNo'>$seatNo</div>";
            endfor;
            ?>
        </div>

        <!-- Display total fare -->
        <div class="total-fare">
            Selected Seats: <span id="selectedSeats">None</span><br>
            Total Fare: ₹<span id="totalFare">0</span>
        </div>

        <!-- Hidden fields to pass seat number and fare to PHP -->
        <input type="hidden" name="seat_number" id="seatInput" required>
        <input type="hidden" name="fare" id="fareInput" value="<?php echo htmlspecialchars($bus['price']); ?>">
        <input type="date" name="booking_date" required>
        <button type="submit">Book & Pay</button>
    </form>
</div>

<script>
// JavaScript for handling seat selection and fare calculation

// Get all seat elements
const seats = document.querySelectorAll('.seat');
// Get DOM elements for selected seat list & total fare
const selectedSeatsEl = document.getElementById('selectedSeats');
const totalFareEl = document.getElementById('totalFare');
// Hidden input fields for sending seat data to PHP
const seatInput = document.getElementById('seatInput');
// Get fare per seat from hidden input
const farePerSeat = parseFloat(document.getElementById('fareInput').value);

let selectedSeats = []; // Array to store selected seat numbers

// Loop through all seat elements
seats.forEach(seat => {
    seat.addEventListener('click', () => {
        // Ignore clicks on booked seats
        if (seat.classList.contains('booked')) return;

        // Toggle seat selection
        seat.classList.toggle('selected');
        const seatNo = seat.dataset.seat; // Get seat number (e.g., S1)

        // If seat is already selected, remove from array
        if (selectedSeats.includes(seatNo)) {
            selectedSeats = selectedSeats.filter(s => s !== seatNo);
        } else {
            // Otherwise, add seat to selection
            selectedSeats.push(seatNo);
        }

        // Update displayed selected seats
        selectedSeatsEl.textContent = selectedSeats.length > 0 ? selectedSeats.join(', ') : 'None';
        // Update total fare
        totalFareEl.textContent = selectedSeats.length * farePerSeat;

        // Update hidden inputs for form submission
        seatInput.value = selectedSeats.join(',');
        document.getElementById('fareInput').value = selectedSeats.length * farePerSeat;
    });
});
</script>
</body>
</html>
