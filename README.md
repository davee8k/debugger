# Debugger

## Description

Simple debugger for php

## Requirements

- PHP 7.1+
- (PHP 5.5 - 8.0 version 0.86 and lower)

## Usage

	new \Debugger\Debugger(
		true,	// work mode [false - disable, 'FILE' - log errors to file, true - display info bar and errors, no logging]
		'./temp/',	// directory for file logs (optional)
		-1	// php error level, -1 is default (optional)
	);

## Logging SQL requests

	Use PDO extension class \Debugger\PDODebugger
