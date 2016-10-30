<?php

namespace VClass\Templates;

interface Renderer
{
    public function render($template, $data = []);
}