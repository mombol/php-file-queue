## [v1.2.1] - 2016-10-24

### Fixed
* Fixed there is no composer psr-4 map configuration


## [v1.2.0] - 2016-10-24

### Change
* Change the FileQueueBase class name


## [v1.1.0] - 2016-10-20

### Fixed
* Fixed the queue data consume get wrong configuration when consume and clean queue

### Add
* Add source code namespace in order to installed by composer

### Change
* Remove some unused tests config file
* Compatible PHP5.3 and windows phpunit tests


## [v1.0.0] - 2016-06-14

### Fixed
* Fixed the `end` method give the wrong parameter when call `filesize`
* Fixed the construction of `QueueDataConsume` lack the config `queueFileName` when construct FileQueue

### Add
* Add the entry of unmount queue file
* Add the entry of clean the queue data In `QueueDataConsume` file
* Can get the current position when pop queue data
* Add PHPUnit test case

### Change
* Remove the destruct method
* Allow generator pop data from queue
* Filter a data CRLF which pop from queue


## [v0.1.0] - 2016-06-12

### Fixed
* Fixed bug of consume queue data when multi-queue share one queue data

### Add

* Move queue data consumption function to a new class
* Add the ability of backup consume data
* Add the ability of read from specify line number where queue start

### Change
* Cancel open track file when queue start
