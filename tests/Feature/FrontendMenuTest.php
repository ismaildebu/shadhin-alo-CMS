<?php

// মোবাইল মেনু: হ্যামবার্গারে খোলে/বন্ধ হয়, সাব-বিভাগ (সারাদেশ) ক্লিকে খোলে।

it('lets the mobile menu close again', function () {
    $css = (string) file_get_contents(public_path('css/frontend.css'));

    // আগের বাগ: .navbar-collapse-এ সরাসরি display:flex বসানো থাকায় মেনু কখনো বন্ধ হতো না
    expect($css)->not->toMatch('/\.navbar-custom \.navbar-collapse\{display:flex/')
        ->and($css)->toMatch('/\.navbar-custom \.navbar-collapse\.show,/')
        ->and($css)->toMatch('/\.navbar-custom \.navbar-collapse\.collapsing\{display:flex/');
});

it('folds the mobile sub-menus until the reader taps them', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('js/nav-submenu.js');

    $mobile = '';
    if (preg_match('/@media \(max-width:991\.98px\)\{(.*?)\n\}/s', $html, $match)) {
        $mobile = $match[1];
    }

    expect($mobile)->toMatch('/\.nav-item\.dropdown > \.dropdown-menu\{\s*display:none/')
        ->and($mobile)->toMatch('/\.nav-item\.dropdown\.is-open > \.dropdown-menu/')
        ->and($mobile)->toMatch('/> \.dropdown-menu\.show\{\s*display:block/');
});

it('leaves the desktop hover rule alone', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toMatch('/@media \(min-width:992px\)\{\s*\.navbar-custom \.nav-item\.dropdown:hover > \.dropdown-menu\{\s*display:block !important/');
});
