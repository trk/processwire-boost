<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost;

final class GuidelineBuilder
{
    public function __construct(private readonly string $projectRoot) {}

    public function build(array $index, string $targetPath): void
    {
        $sections = [];

        $sections[] = '# ProcessWire Core Guidelines';
        $sections[] = 'Generated from core API analysis - ' . date('Y-m-d');
        $sections[] = '';

        $sections = array_merge($sections, $this->generateIdiomaticRules($index));
        $sections = array_merge($sections, $this->generateSecurityRules($index));
        $sections = array_merge($sections, $this->generatePerformanceRules($index));
        $sections = array_merge($sections, $this->generateGroupOverview($index));

        $dir = dirname($targetPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($targetPath, implode("\n\n", $sections) . "\n");
    }

    private function generateIdiomaticRules(array $index): array
    {
        $sections = [];
        $sections[] = '## Idiomatic ProcessWire Patterns';

        $sections[] = '### Page Finding and Retrieval';
        $sections[] = <<<'MD'
- Use `$pages->find($selector)` for multiple pages - returns PageArray
- Use `$pages->get($selector)` for single page - excludes hidden/unpublished unless specified
- Use `$pages->getById($id)` for direct ID lookup - fastest method
- Always check for NullPage: `if ($page->id)`
MD;

        $sections[] = '### Page Saving';
        $sections[] = <<<'MD'
- Use `$page->save()` for single page save
- Use `$pages->save($page)` as alternative
- Use `$pages->saveField($page, "fieldname")` for single field save to reduce overhead
- Hook `saveReady` for pre-save logic, `saved` for post-save
MD;

        $sections[] = '### Page Creation';
        $sections[] = <<<'MD'
- Use `$pages->add($template, $parent, $name)` for new pages
- Set `$page->name` before saving if specific name needed
- Use `$page->setOutputFormatting(false)` when modifying markup before save
MD;

        $sections[] = '### Page Deletion';
        $sections[] = <<<'MD'
- Use `$pages->trash($page)` for soft delete (movable to trash)
- Use `$pages->delete($page, true)` for permanent recursive delete
- Always check for children before delete: hooks will throw if `$recursive=false`
MD;

        $sections[] = '### Cloning';
        $sections[] = <<<'MD'
- Use `$pages->clone($page, $parent, true)` for full recursive clone
- Cloned pages get new IDs but retain field values
- Hook `cloneReady` and `cloned` for custom clone logic
MD;

        return $sections;
    }

    private function generateSecurityRules(array $index): array
    {
        $sections = [];
        $sections[] = '## Security Guidelines';

        $sections[] = '### Input Sanitization';
        $sections[] = <<<'MD'
- ALWAYS sanitize before output: `$sanitizer->text()`, `$sanitizer->email()`, `$sanitizer->url()`
- Use `$sanitizer->selectorValue()` for selector strings
- Use `$input->whitelist()` for known-good values
- Never trust `$_GET`, `$_POST` directly - use `$input->get()`, `$input->post()`
MD;

        $sections[] = '### Access Control';
        $sections[] = <<<'MD'
- Check permissions with `$page->hasPermission("name")` or `$user->hasPermission("name")`
- Use `$user->isLoggedin()` for authentication check
- Use roles for group-based access: `$user->hasRole("editor")`
- Superuser (`$user->isSuperuser()`) bypasses all permission checks
MD;

        $sections[] = '### Page Access';
        $sections[] = <<<'MD'
- Respect page view permissions in selectors: `include=all` or `check_access=0`
- Use `$page->viewable()` to check if current user can view
- Use `$page->editable()` to check edit permission
- Use `$page->deletable()` to check delete permission
MD;

        $sections[] = '### CSRF Protection';
        $sections[] = <<<'MD'
- Use `$session->CSRF->validate()` or `SessionCSRF::validate()` in forms
- Generate tokens: `$session->CSRF->token()`
- Include in forms: `<input type="hidden" name="<?php echo $session->CSRF->getTokenName(); ?>" value="<?php echo $session->CSRF->getTokenValue(); ?>">`
MD;

        $sections[] = '### File Uploads';
        $sections[] = <<<'MD'
- Validate with `$files->validate($file, $options)`
- Use FieldtypeImage/FieldtypeFile for built-in handling
- Never save files to web-accessible paths without validation
- Use `$config->uploadTmpDir` for temp storage
MD;

        return $sections;
    }

    private function generatePerformanceRules(array $index): array
    {
        $sections = [];
        $sections[] = '## Performance Guidelines';

        $sections[] = '### Database Queries';
        $sections[] = <<<'MD'
- Use `$pages->count($selector)` instead of `count($pages->find())`
- Use `$pages->findRaw()` for arrays instead of PageArray objects
- Use `limit=1` in selectors when you only need one page
- Avoid `find()` in loops - fetch all needed at once
MD;

        $sections[] = '### Output Formatting';
        $sections[] = <<<'MD'
- Use `$page->of(false)` to modify unformatted values
- Use `$page->of(true)` before output to apply formatting
- Set `outputFormatting` globally: `$pages->setOutputFormatting(true)`
- Use `getUnformatted("fieldname")` for raw value access
MD;

        $sections[] = '### Caching';
        $sections[] = <<<'MD'
- Use `$cache->get($key, $func)` for expensive operations
- Use `$page->cache()` to enable page-level caching
- Use `$config->cachePaths` for path-based cache
- Clear cache: `$cache->delete($key)` or `$cache->deleteAll()`
MD;

        $sections[] = '### Images';
        $sections[] = <<<'MD'
- Use `$pageimage->size(width, height)` for on-demand resizing
- Use `$pageimage->focusCrop(width, height)` for smart cropping
- Enable variation caching: `$config->imageSizerOptions["sharpening"]`
- Use WebP: `$config->imageSizerOptions["webp"] = true`
MD;

        return $sections;
    }

    private function generateGroupOverview(array $index): array
    {
        $sections = [];
        $sections[] = '## Method Groups Overview';

        $groupedMethods = [];
        foreach ($index as $fqcn => $meta) {
            foreach ($meta['methods'] ?? [] as $name => $m) {
                $group = $m['pw_group'] ?? 'common';
                if (!isset($groupedMethods[$group])) {
                    $groupedMethods[$group] = [];
                }
                $groupedMethods[$group][] = $fqcn . '::' . $name;
            }
        }

        foreach ($groupedMethods as $group => $methods) {
            $sections[] = "### Group: {$group}";
            $sections[] = 'Methods: ' . count($methods);
            $sections[] = 'Examples:';
            foreach (array_slice($methods, 0, 5) as $m) {
                $sections[] = "- `{$m}`";
            }
            if (count($methods) > 5) {
                $sections[] = '- ... and ' . (count($methods) - 5) . ' more';
            }
            $sections[] = '';
        }

        return $sections;
    }
}
