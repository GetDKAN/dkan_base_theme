<?php

/**
 * @file
 * Prints WCAG 2.2 contrast ratios for the pairs the theme renders.
 *
 * Usage: php scripts/contrast.php [--min=4.5] [--tokens=path/to/tokens.css]
 * Text pairs must reach --min; non-text pairs (focus ring) 3:1. Exit 1 if
 * any pair fails. A subtheme passes its own tokens file, which must define
 * every --dbt-color-* it overrides in #rrggbb form.
 */

$min = 4.5;
$tokens = __DIR__ . '/../css/tokens.css';
foreach ($argv as $arg) {
  if (preg_match('/^--min=([\d.]+)$/', $arg, $m)) {
    $min = (float) $m[1];
  }
  elseif (preg_match('/^--tokens=(.+)$/', $arg, $m)) {
    $tokens = $m[1];
  }
}

$css = file_get_contents($tokens);
preg_match_all('/--dbt-color-([a-z-]+):\s*(#[0-9a-f]{6})/i', $css, $matches, PREG_SET_ORDER);
$colors = [];
foreach ($matches as $m) {
  $colors[$m[1]] = $m[2];
}

// [foreground, background, where it is used, minimum ratio (default --min)].
$pairs = [
  ['focus', 'surface', 'focus ring on the page', 3.0],
  ['focus', 'surface-alt', 'focus ring on alt surfaces', 3.0],
  ['primary-contrast', 'primary', 'focus ring gap on primary', 3.0],
  ['text', 'surface', 'body text'],
  ['text', 'surface-alt', 'text on cards and alt surfaces'],
  ['text-muted', 'surface', 'muted text'],
  ['text-muted', 'surface-alt', 'muted text on alt surfaces'],
  ['link', 'surface', 'links'],
  ['link', 'surface-alt', 'links on alt surfaces'],
  ['link-hover', 'surface', 'link hover'],
  ['primary-contrast', 'primary', 'buttons, header'],
  ['primary-contrast', 'success', 'success badges'],
  ['primary-contrast', 'error', 'error badges and messages'],
  ['success', 'surface', 'success text'],
  ['error', 'surface', 'error text'],
];

/**
 * Relative luminance of a hex color.
 */
function luminance(string $hex): float {
  $rgb = sscanf($hex, '#%02x%02x%02x');
  $channels = array_map(function (int $c): float {
    $c /= 255;
    return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
  }, $rgb);
  return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

/**
 * Contrast ratio between two hex colors.
 */
function ratio(string $a, string $b): float {
  $l1 = luminance($a);
  $l2 = luminance($b);
  return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
}

$failed = FALSE;
foreach ($pairs as $pair) {
  [$fg, $bg, $use] = $pair;
  $required = $pair[3] ?? $min;
  if (!isset($colors[$fg], $colors[$bg])) {
    printf("MISSING  %s on %s\n", $fg, $bg);
    $failed = TRUE;
    continue;
  }
  $r = ratio($colors[$fg], $colors[$bg]);
  $ok = $r >= $required;
  $failed = $failed || !$ok;
  printf("%s  %5.2f  %s on %s (%s, min %.1f)\n", $ok ? 'PASS' : 'FAIL', $r, $fg, $bg, $use, $required);
}
exit($failed ? 1 : 0);
