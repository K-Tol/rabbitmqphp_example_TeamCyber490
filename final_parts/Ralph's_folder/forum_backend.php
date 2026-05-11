<?php
declare(strict_types=1);




header('Content-Type: application/json');


//  db settings
$DB_HOST = 'localhost';
$DB_USER = 'db_user';
$DB_PASS = 'passwd123';
$DB_NAME = 'movie_forum';


function send_json(array $data): void
{
    echo json_encode($data);
    exit(0);
}

try {

    // Only allow POST requests.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_json([
            'ok' => false,
            'message' => 'Only POST allowed'
        ]);
    }
    // connects to db
   
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

    if ($conn->connect_error) {
        send_json([
            'ok' => false,
            'message' => 'Database connection failed: ' . $conn->connect_error
        ]);
    }

    $conn->set_charset('utf8mb4');

    // READ REQUEST TYPE
    
    $type = strtolower(trim($_POST['type'] ?? ''));

    if ($type === '') {
        send_json([
            'ok' => false,
            'message' => 'Missing type'
        ]);
    }

    switch ($type) {
        // Aadd post
        case 'addpost':

            $movieTitle = trim($_POST['movie_title'] ?? '');
            $userName = trim($_POST['user_name'] ?? '');
            $userComment = trim($_POST['user_comment'] ?? '');

            if ($movieTitle === '' || $userName === '' || $userComment === '') {
                send_json([
                    'ok' => false,
                    'message' => 'Missing movie title, user name, or discussion post'
                ]);
            }

            $sql = 'INSERT INTO forum_posts (movie_title, user_name, user_comment)
                    VALUES (?, ?, ?)';

            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                send_json([
                    'ok' => false,
                    'message' => 'Failed to prepare add post query'
                ]);
            }

            $stmt->bind_param('sss', $movieTitle, $userName, $userComment);

            if (!$stmt->execute()) {
                send_json([
                    'ok' => false,
                    'message' => 'Failed to add post'
                ]);
            }

            send_json([
                'ok' => true,
                'status' => 'added',
                'post_id' => $stmt->insert_id,
                'message' => 'Discussion post added'
            ]);

            break;


        
        // comments , add comments 
    
        case 'addcomment':

            $postId = (int)($_POST['post_id'] ?? 0);
            $commentName = trim($_POST['comment_name'] ?? '');
            $commentText = trim($_POST['comment_text'] ?? '');

            if ($postId <= 0 || $commentName === '' || $commentText === '') {
                send_json([
                    'ok' => false,
                    'message' => 'Missing post id, comment name, or comment text'
                ]);
            }

            $sql = 'INSERT INTO forum_comments (post_id, comment_name, comment_text)
                    VALUES (?, ?, ?)';

            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                send_json([
                    'ok' => false,
                    'message' => 'Failed to prepare add comment query'
                ]);
            }

            $stmt->bind_param('iss', $postId, $commentName, $commentText);

            if (!$stmt->execute()) {
                send_json([
                    'ok' => false,
                    'message' => 'Failed to add comment'
                ]);
            }

            send_json([
                'ok' => true,
                'status' => 'added',
                'comment_id' => $stmt->insert_id,
                'message' => 'Comment added'
            ]);

            break;
    
        // This returns every post and places the matching  comments inside each post.
        
        case 'getposts':

            $posts = [];

            $postSql = '
                SELECT
                    p.id,
                    p.movie_title,
                    p.user_name,
                    p.user_comment,
                    p.created_at,
                    COUNT(c.id) AS comment_count
                FROM forum_posts p
                LEFT JOIN forum_comments c ON p.id = c.post_id
                GROUP BY p.id, p.movie_title, p.user_name, p.user_comment, p.created_at
                ORDER BY p.created_at DESC
            ';

            $postResult = $conn->query($postSql);

            if (!$postResult) {
                send_json([
                    'ok' => false,
                    'message' => 'Failed to load posts'
                ]);
            }

            while ($row = $postResult->fetch_assoc()) {
                $row['comments'] = [];
                $posts[(int)$row['id']] = $row;
            }

            $commentSql = '
                SELECT id, post_id, comment_name, comment_text, created_at
                FROM forum_comments
                ORDER BY created_at ASC
            ';

            $commentResult = $conn->query($commentSql);

            if (!$commentResult) {
                send_json([
                    'ok' => false,
                    'message' => 'Failed to load comments'
                ]);
            }

            while ($comment = $commentResult->fetch_assoc()) {
                $postId = (int)$comment['post_id'];

                if (isset($posts[$postId])) {
                    $posts[$postId]['comments'][] = $comment;
                }
            }

            send_json([
                'ok' => true,
                'posts' => array_values($posts),
                'message' => 'Posts loaded'
            ]);

            break;

        // delete post
        
        case 'deletepost':

            $postId = (int)($_POST['post_id'] ?? 0);

            if ($postId <= 0) {
                send_json([
                    'ok' => false,
                    'message' => 'Missing post id'
                ]);
            }

            $sql = 'DELETE FROM forum_posts WHERE id = ?';
            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                send_json([
                    'ok' => false,
                    'message' => 'Failed to prepare delete post query'
                ]);
            }

            $stmt->bind_param('i', $postId);

            if (!$stmt->execute()) {
                send_json([
                    'ok' => false,
                    'message' => 'Failed to delete post'
                ]);
            }

            send_json([
                'ok' => true,
                'status' => 'deleted',
                'message' => 'Discussion post deleted'
            ]);

            break;

        // unknown request type
        
        default:

            send_json([
                'ok' => false,
                'status' => 'error',
                'message' => 'Unknown request type'
            ]);

            break;
    }

} catch (Throwable $e) {

    // If something unexpected happens, still return JSON.
    send_json([
        'ok' => false,
        'message' => $e->getMessage()
    ]);
}
?>
