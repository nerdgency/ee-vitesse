<?php

namespace Nerdgency\Vitesse\Tags;

use ExpressionEngine\Service\Addon\Controllers\Tag\AbstractRoute;

/**
 * Class Tags
 *
 * Handles the injection of CSS and JS files into templates using Vite, 
 * including support for hot module replacement (HMR).
 */
class Tags extends AbstractRoute
{
    /**
     * The filename that indicates Vite's hot module replacement is active.
     *
     * @var string
     */
    protected string $hotFileName = '';

    /**
     * Stores the generated HTML tags.
     *
     * @var string
     */
    protected string $tags = '';

    /**
     * Directory where built assets are stored.
     *
     * @var string
     */
    protected string $buildDirectory = '';

    /**
     * Manifest file content as an associative array.
     *
     * @var array
     */
    protected array $manifest = [];

    /**
     * Main process method that generates the tags for the given files.
     *
     * @return string|null Returns the generated tags or null if no files are provided.
     */
    public function process(): ?string
    {
        $files = array_filter(explode('|', ee()->TMPL->fetch_param('files', '')));
        
        if (empty($files)) {
            return null;
        }

        $this->setHotFileName(ee()->TMPL->fetch_param('hot_file_name', 'hot'));
        $this->setBuildDirectory(ee()->TMPL->fetch_param('build_directory', 'build'));
        $this->setManifest("./{$this->getBuildDirectory()}/manifest.json");

        $hot = $this->getHotContent();

        $this->generateTags($files, $hot);

        if ($this->isHot()) {
            $this->injectViteClient();
        }

        return $this->getTags();
    }

    /**
     * Categorizes files and generates the corresponding tags.
     *
     * @param array $files List of file names.
     * @param string|null $hot The URL for the hot module server, if active.
     */
    protected function generateTags(array $files, ?string $hot): void
    {
        foreach ($files as $file) {
            $extension = pathinfo($file, PATHINFO_EXTENSION);

            if ($extension === 'css') {
                $this->generateTag('link', $file, $hot);
            } elseif ($extension === 'js') {
                $this->generateTag('script', $file, $hot);
            }
        }
    }

    /**
     * Generates a tag for the given file and appends it to the output.
     *
     * @param string $type The type of tag ('link' or 'script').
     * @param string $file The file name.
     * @param string|null $hot The URL for the hot module server, if active.
     */
    protected function generateTag(string $type, string $file, ?string $hot): void
    {
        $path = $hot ? "{$hot}/{$file}" : "{$this->getBuildDirectory()}/{$this->getManifest($file)}";

        if ($type === 'link') {
            $this->addTag("<link rel=\"stylesheet\" href=\"{$path}\" />");
        } elseif ($type === 'script') {
            $this->addTag("<script type=\"module\" src=\"{$path}\"></script>");
        }
    }

    /**
     * Injects the Vite client script into the output when hot module replacement is active.
     */
    protected function injectViteClient(): void
    {
        $this->addTag("<script type=\"module\" src=\"{$this->getHotContent()}/@vite/client\"></script>");
    }

    /**
     * Sets the directory where built assets are stored.
     *
     * @param string $directory The directory path.
     */
    public function setBuildDirectory(string $directory): void
    {
        $this->buildDirectory = $directory;
    }

    /**
     * Returns the directory where built assets are stored.
     *
     * @return string
     */
    public function getBuildDirectory(): string
    {
        return $this->buildDirectory;
    }

    /**
     * Loads the manifest file content into an associative array.
     *
     * @param string $file Path to the manifest file.
     */
    public function setManifest(string $file): void
    {
        if (file_exists($file)) {
            $this->manifest = (array) json_decode(file_get_contents($file), true);
        }
    }

    /**
     * Retrieves the file path from the manifest for the given file.
     *
     * @param string $path The relative file path.
     * @return string The file path from the manifest or an empty string if not found.
     */
    public function getManifest(string $path): string
    {
        return $this->manifest[$path]['file'] ?? '';
    }

    /**
     * Checks if hot module replacement is active.
     *
     * @return bool True if hot module replacement is active, false otherwise.
     */
    public function isHot(): bool
    {
        return file_exists($this->getHotFileName());
    }

    /**
     * Retrieves the content of the hot file if hot module replacement is active.
     *
     * @return string|null The hot file content or null if HMR is not active.
     */
    public function getHotContent(): ?string
    {
        return $this->isHot() ? file_get_contents($this->getHotFileName()) : null;
    }

    /**
     * Returns the filename used to indicate hot module replacement.
     *
     * @return string
     */
    public function getHotFileName(): string
    {
        return $this->hotFileName;
    }

    /**
     * Sets the filename used to indicate hot module replacement.
     *
     * @param string $name The filename.
     */
    public function setHotFileName(string $name): void
    {
        $this->hotFileName = $name;
    }

    /**
     * Appends an HTML tag to the output.
     *
     * @param string $tag The HTML tag.
     */
    public function addTag(string $tag): void
    {
        $this->tags .= $tag;
    }

    /**
     * Returns all generated HTML tags as a string.
     *
     * @return string
     */
    public function getTags(): string
    {
        return $this->tags;
    }
}