# Next Builds Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 2.0.0 - 2024-10-21
### Added
- New custom event for detecting entry status changes 
- New event listener for custom event for triggering revalidates
- New command for running check to look for entries that have changed their status

## 1.2.1 - 2024-10-22
### Changed
- Changed Craft::info() to Craft::warning() so it gets picked up in non-devMode

## 1.2.0 - 2024-7-22
### Changed
- Removed `castiron` from namespace, removed authorship comments, etc.

## 1.1.3 - 2024-7-18
### Fixed
- Deprecated class swap now works correctly by traversing the event target object with the underlying element and in the case of it being a NeoBlock, it's owner (parent)

## 1.1.2 - 2024-6-28
### Fixed
- When a page is updated (enabled, disabled, deleted) the appropriate query param is sent to revalidate the navigation menu in the client

## 1.1.1 - 2024-6-27
### Fixed
- Revalidate request is now firing after entry-save event trigger completes DB update

## 1.1.0 - 2024-06-01
### Removed
- Condition for only revalidated on save when the post is enabled

## 1.0.0 - 2022-09-22
### Added
- Initial release
