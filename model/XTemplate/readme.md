# Compiled XTemplate

XCTemplate class is (partial) implementation of original XTemplate. It uses compiled php files to speedup template
rendering.

All supported features are listed below (note that support is highly limited).

## Methods support

- assign
- parse
- out
- text

## Features support

- basic blocks and blocks nesting ( <!-- begin: something --> and <!-- end: something --> )
- variables (only simple variables without file including)

new features:

- parseEach method
- simple object getters (TODO interface & documentation)

## Basic usage

Assume following files and corresponding output:

### my-template.xtpl

    <!-- begin: page -->
      <h1>Welcome to our page</h1>
      <p>Our clients are</p>
      <!-- begin: client -->
        <p>{firstname} <b>{surname}</b></p>
      <!-- end: client -->
      <p>bye</p>
    <!-- end: page -->

### test.php

    $xtpl = new XCTemplate('my-template.xtpl');
    $xtpl->assign(array(
      'firstname' => 'john',
      'surname' => 'doe'
    ));
    $xtpl->parse('page.client');
    $xtpl->assign(array(
      'firstname' => 'mary',
      'surname' => 'sue'
    ));
    $xtpl->parse('page.client');
    $xtpl->parse('page');
    $xtpl->out('page');

### output:

      <h1>Welcome to our page</h1>
      <p>Our clients are</p>
        <p>john <b>doe</b></p>
        <p>mary <b>sue</b></p>
      <p>bye</p>

## Experiments and notes

- Test speedup on optimal example (100 blocks where batch processing is possible) is about 5.5 times
- When batching is not possible, performance difference between batching-aware and -unaware techniques is next to zero
- Calling die() between first parse() and out() will cause problems because of auto flush of any buffered contents
