<?php

namespace App\Src;

use App\Src\OutputFormats;

class Process
{
    private string $query;
    private string $outputTitle = '%(title)s';
    private string $startStop = '';
    private string $tmpPath = '/tmp/';

    public function __construct(
        private string $url = '',
        private int $startPosition = -1,
        private int $endPosition = -1,
        private OutputFormats $format = OutputFormats::MP3,
    )
    { }

    #region Getters/Setters
    public function setUrl(string $url): void
    {
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $this->url = $url;
        }
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setFormat(OutputFormats $format): void
    {
        $this->format = $format;
    }

    public function getFormat(): OutputFormats
    {
        return $this->format;
    }
    
    public function setStartPosition(int $startPosition): void
    {
        $this->startPosition = $startPosition;
    }

    public function getStartPosition(): int
    {
        return $this->startPosition;
    }

    public function setEndPosition(int $endPosition): void
    {
        $this->endPosition = $endPosition;
    }

    public function getEndPosition(): int
    {
        return $this->endPosition;
    }
    #endregion



    #region Private methods
    private function setStartStop(): string
    {
        if ($this->startPosition >= 0) {
            $this->startStop .= '--start '.$this->startPosition;
        }
    
        if ($this->endPosition >= 0) {
            $this->startStop  .= ' --end '.$this->endPosition;
        }

        return $this->startStop;
    }
    
    private function setQuery(): void
    {  
        $this->query = sprintf(
            "yt-dlp -o %s'%s'.%s %s %s %s --restrict-filenames --embed-thumbnail --embed-metadata",
            $this->tmpPath,
            $this->outputTitle,
            $this->format->getExtension(),
            $this->format->getQueryString(),
            $this->setStartStop(),
            $this->url
        );
    }
    #endregion



    #region General methods
    public function getQuery(): string
    {
        $this->setQuery();

        return $this->query;
    }
    #endregion
}