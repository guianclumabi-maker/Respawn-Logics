const fs = require('fs');
let code = fs.readFileSync('backend/controllers/IAMController.php', 'utf8');

// For the error masking blocks:
// HEAD has:
// <<<<<<< HEAD
//                     echo json_encode(['success' => false, 'error' => $e->getMessage()]);
// =======
//                     error_log('[' . __CLASS__ . '] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
//             http_response_code(500);
//             echo json_encode(['success' => false, 'error' => 'An internal error occurred. Please try again.']);
// >>>>>>> main
// We want to KEEP the main block.
code = code.replace(/<<<<<<< HEAD\r?\n\s*echo json_encode\(\['success' => false, 'error' => \$e->getMessage\(\)\]\);\r?\n=======\r?\n([\s\S]*?)>>>>>>> main/g, '$1');

// For the mailer block:
// <<<<<<< HEAD
// =======
//                     try {
//                         require_once __DIR__ . '/../services/Mailer.php';
// ...
//                     }
// >>>>>>> main
// We want to KEEP the main block.
code = code.replace(/<<<<<<< HEAD\r?\n=======\r?\n([\s\S]*?)>>>>>>> main/g, '$1');

// Double check if there are any remaining <<<<<<<
if (code.includes('<<<<<<<')) {
    console.error("Still have conflicts!");
    console.log(code.match(/<<<<<<<[\s\S]*?>>>>>>>/g));
} else {
    fs.writeFileSync('backend/controllers/IAMController.php', code);
    console.log("Resolved IAMController.php!");
}
