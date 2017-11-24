<?php

// @codeCoverageIgnoreStart
namespace Bkwld\LaravelPug;

use Exception;
use Phug\Util\Exception\LocatedException;
use Pug\Pug;

trait ExceptionHandlerTrait
{
    public function filterErrorResponse(Exception $exception, $request, $response)
    {
        if ($this->container->has('laravel-pug.pug')) {
            /** @var Pug $pug */
            $pug = $this->container->get('laravel-pug.pug');
            try {
                $compiler = $pug->getCompiler();
                $exception = $compiler->getFormatter()->getDebugError(
                    $exception,
                    file_get_contents($exception->getFile()),
                    $compiler->getPath()
                );
            } catch (\Throwable $caughtException) {
                $exception = $caughtException;
            } catch (\Exception $caughtException) {
                $exception = $caughtException;
            }
        }
        if (!$request->expectsJson() && $exception instanceof LocatedException) {
            $className = get_class($exception->getPrevious());
            $line = $exception->getLocation()->getLine();
            $path = realpath($exception->getLocation()->getPath());
            $content = $response->getContent();
            $content = str_replace('Phug\\Util\\Exception\\LocatedException', $className, $content);
            $posNamespace = max(0, strrpos($className, '\\'));

            $content = preg_replace('/
                <div\s+class="exc-title">
                    ([^<]+)<span\s+class="exc-title-primary">LocatedException<\/span>\s*
                <\/div>
            /x', '<div class="exc-title">' .
                mb_substr($className, 0, $posNamespace) .
                '<span class="exc-title-primary">' . mb_substr($className, $posNamespace) . '</span></div>', $content, 1);
            $content = preg_replace_callback('/
                <span\s+class="frame-class">(.+?)<\/span>
            /x', function ($match) use ($className) {
                $input = trim(strip_tags($match[1]));
                if ($input !== 'Phug\\Util\\Exception\\LocatedException') {
                    return $match[0];
                }

                return '<span class="frame-class"><div class="delimiter">' .
                    implode('</div>\\<div class="delimiter">', explode('\\', $className)) .
                    '</div></span>';
            }, $content, 1);
            $content = preg_replace_callback('/
                (<div\s+class="frame-file">\s*
                    <div\s+class="delimiter">)([^<]+)(<\/div><!--\s*
                    --><span\s+class="frame-line">)(\d+)(<\/span>)(\s*
                <\/div>)
            /x', function ($match) use ($exception, $line, $path) {
                $base = realpath(__DIR__ . '/../..');
                if (strpos($path, $base) === 0) {
                    $path = '&hellip;' . mb_substr($path, mb_strlen($base));
                }

                return $match[1] . $path . $match[3] .
                    $line . $match[5] . ' offset ' . $exception->getLocation()->getOffset() .
                    $match[6];
            }, $content, 1);
            $content = preg_replace_callback('/
                (<pre\s+id="frame-code-linenums-0"\s+class="code-block\s+linenums:)(\d+)(">)
                    ([^<]+)
                (<\/pre>)
            /x', function ($match) use ($path, $line) {
                $code = [];
                $source = explode("\n", file_get_contents($path));
                $before = 19;
                $after = 7;
                $start = max(0, $line - $before);
                for ($i = $start; $i < $line + $after; $i++) {
                    if (isset($source[$i])) {
                        $code[] = $source[$i];
                    }
                }
                $code = implode("\n", $code);

                return $match[1] . ($start + 1) . $match[3] . $code . $match[5];
            }, $content);
            $response->setContent($content);
        }

        return $response;
    }
}

// @codeCoverageIgnoreEnd
