<?php
namespace App\Controllers;
use App\Repositories\BookRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
final class BookController
{
    public function __construct(private BookRepository $books)
    {
    }
    public function index(Request $r, Response $s): Response
    {
        $p = $r->getQueryParams();
        $rows = $this->books->all((string) ($p['q'] ?? ''), (int) ($p['limit'] ?? 0));
        return $this->json($s, ['count' => count($rows), 'data' => $rows]);
    }
    public function show(Request $r, Response $s, array $a): Response
    {
        $book = $this->books->find((int) $a['id']);
        return $book ? $this->json($s, $book)
            : $this->json($s, ['error' => 'not found'], 404);
    }
    public function create(Request $req, Response $res): Response
    {
        $body = (array) ($req->getParsedBody() ?? []);
        $auth = (array) $req->getAttribute('auth', []);
        $userId = (int) ($auth['sub'] ?? 0);

        // 1. Validate the incoming book data using your whitelist Validator helper
        $errors = (new \App\Validation\Validator())
            ->required('title', 'author', 'year')
            ->field('title', \App\Validation\Validator::nonEmptyString(200), 'title must be 1-200 chars')
            ->field('author', \App\Validation\Validator::nonEmptyString(150), 'author must be 1-150 chars')
            ->field('year', \App\Validation\Validator::intRange(1000, (int)date('Y')), 'year must be 1000..now')
            ->field('genre', \App\Validation\Validator::nonEmptyString(80), 'genre must be ≤ 80 chars')
            ->validate($body);

        if ($errors) {
            return $this->json($res, ['errors' => $errors], 400);
        }

        // 2. Delegate database insertions to your BookRepository and pass the user ID
        $newBookId = $this->books->create([
            'title' => $body['title'],
            'author' => $body['author'],
            'year' => $body['year'],
            'genre' => $body['genre'] ?? 'Uncategorised'
        ], $userId);

        // 3. Fetch the newly created book and return it safely
        $newBook = $this->books->find($newBookId);

        return $this->json($res, [
            'message' => 'Book created successfully',
            'data' => $newBook
        ], 201);
    }
    /* update() and delete() follow the same pattern — see solution */

    public function update(Request $req, Response $res, array $args): Response
    {
        $id = (int) $args['id'];
        $book = $this->books->find($id);
        if (!$book)
            return $this->json($res, ['error' => 'Not found'], 404);
        $auth = (array) $req->getAttribute('auth', []);
        $isOwner = (int) $book['created_by'] === (int) ($auth['sub'] ?? 0);
        $isAdmin = ($auth['role'] ?? 'member') === 'admin';
        if (!$isOwner && !$isAdmin)
            return $this->json($res, ['error' => 'Forbidden'], 403);
        $id = (int) ($args['id'] ?? 0);

        // 1. Check if the book exists in the database
        $current = $this->books->find($id);
        if ($current === null) {
            return $this->json($res, ['error' => "Book {$id} not found"], 404);
        }

        $body = (array) ($req->getParsedBody() ?? []);

        // 2. Validate incoming fields (partial validation allowed for updates)
        $errors = $this->validate($body, false);
        if (!empty($errors)) {
            return $this->json($res, ['errors' => $errors], 400);
        }

        // 3. Update fields in the database
        $this->books->update($id, $body);

        // 4. Fetch the newly updated book to return it
        $updatedBook = $this->books->find($id);

        return $this->json($res, ['message' => 'Book updated', 'data' => $updatedBook]);
    }

    public function delete(Request $req, Response $res, array $args): Response
    {
        $auth = (array) $req->getAttribute('auth', []);
        if (($auth['role'] ?? 'member') !== 'admin') {
            return $this->json($res, ['error' => 'Admins only'], 403);
        }
        $id = (int) ($args['id'] ?? 0);

        // 1. Find the book first to confirm existence and capture data
        $deleted = $this->books->find($id);
        if ($deleted === null) {
            return $this->json($res, ['error' => "Book {$id} not found"], 404);
        }

        // 2. Delete it from the database
        $this->books->delete($id);

        return $this->json($res, ['message' => 'Book deleted', 'data' => $deleted]);
    }

    private function validate(array $b, bool $requireAll): array
    {
        $errors = [];

        // 1. If creating a new book (requireAll is true), check for missing fields
        if ($requireAll) {
            if (empty($b['title']))
                $errors['title'] = 'Title is required.';
            if (empty($b['author']))
                $errors['author'] = 'Author is required.';
            if (empty($b['genre']))
                $errors['genre'] = 'Genre is required.';
            if (empty($b['year']))
                $errors['year'] = 'Year is required.';
        }

        // 2. If fields are provided, check their format using $b
        if (array_key_exists('title', $b) && empty(trim((string) $b['title']))) {
            $errors['title'] = 'Title cannot be blank.';
        }

        if (array_key_exists('author', $b) && empty(trim((string) $b['author']))) {
            $errors['author'] = 'Author cannot be blank.';
        }

        if (array_key_exists('year', $b)) {
            $year = (int) $b['year'];
            if ($year < 1000 || $year > (int) date('Y') + 5) {
                $errors['year'] = 'Please provide a valid publication year.';
            }
        }

        return $errors;
    }

    private function json(Response $r, $data, int $status = 200): Response
    {
        $r->getBody()->write(json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ));
        return $r->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }
}
