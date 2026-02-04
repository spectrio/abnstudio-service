<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

use Illuminate\Support\Facades\DB;

class TemplatePlaceholderFixer
{
    private function normalizePlaceholders($content)
    {
        $pattern = '/{{([^}]*(?:<[^>]+>[^}]*)*)}}/';

        $normalized = preg_replace_callback($pattern, function($matches) {
            $innerContent = $matches[1];
            $text = strip_tags($innerContent);

            preg_match('/style="([^"]*)"/', $innerContent, $styleMatch);
            $style = isset($styleMatch[1]) ? $styleMatch[1] : '';
            $style = preg_replace('/background-color:\s*rgba\(0,\s*0,\s*0,\s*0\);?/', '', $style);
            $style = trim($style);

            if ($style) {
                return '<span style="' . $style . '">{{' . $text . '}}</span>';
            }
            return '{{' . $text . '}}';
        }, $content);
        
        return $normalized;
    }

    public function fixAllTemplates()
    {
        echo "Starting template placeholder normalization...\n\n";

        $templates = DB::table('templates')
            ->select('tid', 'name', 'template')
            ->whereNotNull('template')
            ->where('template', '!=', '')
            ->get();

        $total = count($templates);
        $updated = 0;
        $skipped = 0;

        foreach ($templates as $template) {
            echo "Processing TID {$template->tid}: {$template->name}... ";

            if (strpos($template->template, '{{') === false) {
                echo "SKIPPED (no placeholders)\n";
                $skipped++;
                continue;
            }

            $normalized = $this->normalizePlaceholders($template->template);

            if ($normalized !== $template->template) {
                DB::table('templates')
                    ->where('tid', $template->tid)
                    ->update(['template' => $normalized]);
                
                echo "UPDATED\n";
                $updated++;
            } else {
                echo "SKIPPED (already clean)\n";
                $skipped++;
            }
        }

        echo "\n========================================\n";
        echo "SUMMARY:\n";
        echo "Total templates: {$total}\n";
        echo "Updated: {$updated}\n";
        echo "Skipped: {$skipped}\n";
        echo "========================================\n";
    }
}

$fixer = new TemplatePlaceholderFixer();
$fixer->fixAllTemplates();