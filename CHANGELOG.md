## [v1.2.0] - 2016-10-20

### Change
* change the FileQueueBase class name


## [v1.1.0] - 2016-10-20

### Fixed
* fixed the queue data consume get wrong configuration when consume and clean queue

### Add
* add source code namespace in order to installed by composer

### Change
* remove some unused tests config file
* compatible PHP5.3 and windows phpunit tests


## [v1.0.0] - 2016-06-14

### Fixed
* fixed the `end` method give the wrong parameter when call `filesize`
* fixed the construction of `QueueDataConsume` lack the config `queueFileName` when construct FileQueue

### Add
* add the entry of unmount queue file
* add the entry of clean the queue data In `QueueDataConsume` file
* can get the current position when pop queue data
* add PHPUnit test case

### Change
* remove the destruct method
* allow generator pop data from queue
* filter a data CRLF which pop from queue


## [v0.1.0] - 2016-06-12

### Fixed
* fixed bug of consume queue data when multi-queue share one queue data

### Add

* move queue data consumption function to a new class
* add the ability of backup consume data
* add the ability of read from specify line number where queue start

### Change
* cancel open track file when queue start
