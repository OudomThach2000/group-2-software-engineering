<?php
/**
 * Cyclomatic complexity of every function in the PHP files given (SWEBOK Ch5, 2.4.1).
 *
 * McCabe's V(G) is counted as 1 plus one for each decision: if, elseif, for, foreach,
 * while, case, catch, ?:, ??, && and ||. Code outside any function, such as the body of a
 * page, is reported as (page). A function with V(G) above 10 is harder to understand and
 * test, so it is the first candidate for refactoring.
 *
 * Run from the project root:
 *     php tools/complexity.php app/src/*.php app/includes/*.php app/public/*.php \
 *         app/public/staff/*.php app/public/supervisor/*.php
 *
 * Added by Pichponleur Pen on 23 October 2026 for the v6 maintenance measures.
 */

const DECISIONS = [T_IF, T_ELSEIF, T_FOR, T_FOREACH, T_WHILE, T_CASE, T_CATCH,
                   T_BOOLEAN_AND, T_BOOLEAN_OR, T_LOGICAL_AND, T_LOGICAL_OR, T_COALESCE];

/** [name => complexity] for each function in $file, plus '(page)' for code outside functions. */
function complexity_of_file(string $file): array
{
    $tokens  = token_get_all(file_get_contents($file));
    $result  = ['(page)' => 1];
    $stack   = [];          // open functions: [name, brace depth when the body started]
    $depth   = 0;
    $pending = null;        // a function name waiting for its opening brace

    foreach ($tokens as $i => $t) {
        $id   = is_array($t) ? $t[0] : null;
        $text = is_array($t) ? $t[1] : $t;

        if ($id === T_FUNCTION) {
            // The next string token is the name; closures get a generated name.
            for ($j = $i + 1; isset($tokens[$j]); $j++) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) { $pending = $tokens[$j][1]; break; }
                if ($tokens[$j] === '(') { $pending = 'closure@' . $tokens[$i][2]; break; }
            }
            continue;
        }
        if ($text === '{' || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
            $depth++;
            if ($pending !== null && $text === '{') {
                $stack[] = [$pending, $depth];
                $result[$pending] = 1;
                $pending = null;
            }
            continue;
        }
        if ($text === ';' && $pending !== null) {   // abstract or interface method: no body
            $pending = null;
            continue;
        }
        if ($text === '}') {
            if ($stack && end($stack)[1] === $depth) {
                array_pop($stack);
            }
            $depth--;
            continue;
        }
        // A ternary ? is followed by a space or by : (short ternary); a nullable type such as
        // ?string is followed directly by the type name, so it is not a decision.
        $next = $tokens[$i + 1] ?? null;
        $isTernary = $text === '?' && ($next === ':' || (is_array($next) && $next[0] === T_WHITESPACE));
        if (in_array($id, DECISIONS, true) || $isTernary) {
            $owner = $stack ? end($stack)[0] : '(page)';
            $result[$owner]++;
        }
    }
    return $result;
}

$files = array_slice($argv, 1);
if (!$files) {
    fwrite(STDERR, "Usage: php tools/complexity.php file.php ...\n");
    exit(1);
}

$all = [];
foreach ($files as $file) {
    foreach (complexity_of_file($file) as $name => $vg) {
        if ($name === '(page)' && $vg === 1 && str_contains($file, '/src/')) {
            continue;   // logic files have no page code
        }
        $all[] = [$file, $name, $vg];
    }
}
usort($all, fn($a, $b) => $b[2] <=> $a[2] ?: strcmp($a[0] . $a[1], $b[0] . $b[1]));

printf("%-40s %-28s %5s\n", 'File', 'Function', 'V(G)');
foreach ($all as [$file, $name, $vg]) {
    printf("%-40s %-28s %5d%s\n", $file, $name, $vg, $vg > 10 ? '  over 10' : '');
}
$values = array_column($all, 2);
printf("\n%d units, average V(G) %.1f, highest %d, over 10: %d\n",
    count($values), array_sum($values) / max(1, count($values)), max($values), count(array_filter($values, fn($v) => $v > 10)));
