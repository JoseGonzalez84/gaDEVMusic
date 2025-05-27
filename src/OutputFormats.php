<?php

namespace App\Src;

enum OutputFormats: string
{
    case MP3 = "mp3";
    case WEBM = "webm";
    case MP4 = "mp4";

    public function getExtension(): string
    {
        return match ($this) {
            OutputFormats::MP3 => 'mp3',
            OutputFormats::WEBM => 'webm',
            OutputFormats::MP4 => 'mp4',
            default => 'mp3'
        };
    }

    public function getQueryString(): string
    {
        return match ($this) {
            OutputFormats::MP3 => '-x --audio-format mp3',
            OutputFormats::WEBM => '-f webm',
            OutputFormats::MP4 => '-f mp4',
            default => '-x --audio-format mp3'
        };
    }
}