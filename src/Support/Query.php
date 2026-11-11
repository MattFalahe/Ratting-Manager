<?php

namespace RattingManager\Support;

final class Query
{
    /**
     * A query parameter as text. An address made by hand can send a list
     * (?period[]=x), and that reads as if nothing was sent.
     */
    public static function text($request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : '';
    }
}
