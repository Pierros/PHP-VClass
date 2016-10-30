<?php

namespace VClass\Page;

interface PageReader
{
    public function readBySlug($slug);
}