# Cronbutton

A really simple webpage (the 'button') and script to be cronned (the 'cron') to facilitate the 
triggering of jobs by clicking a button on a website without needing to make that website able 
to actually _run_ those commands. 

There's three components:

* public_html/cronbutton.php is a PHP page that'll query the `tasks` table in a database and present
    buttons for a visitor to click based on its contents. Button-clicks will translate into entries in
    the `todo` table. Only one instance of any 'task' can be queued.

* ./cronrunner is a (perl) script that'll query the `todo` table and, on each run, execute the
     oldest not-yet-run job from the list, and exit. 

* A MySQL db to store the available tasks and the todo list

It is intended that `cronrunner` be cronned frequently enough that your button presses turn into
scripts about as quickly as you want them to.

This is _simple_ in the sense of being not-very-capable:

* There is no locking, nothing to prevent the runner running twice and doing the same job twice. Use
    something else for this (I use my [locker](https://github.com/BigRedS/locker) script for this, but
    bafflingly I also wrote [crapper](https://github.com/BigRedS/crapper) which does a similar thing.

* 'Installation' involves manually creating a couple of tables and some grants, and inserting into a
    table; the principle of least-privilege means this tool isn't supposed to be able to mange which
    tasks exist or what they are. I use the MySQL shell.

* There is no tooling for adding or modifying the tasks; it's felt that the MySQL shell ought to be 
  flexible enough for this.

## Install

You need two tables:

    CREATE TABLE `tasks` (
      `id` int(4) NOT NULL AUTO_INCREMENT,
      `name` varchar(30) NOT NULL,
      `description` varchar(120) DEFAULT NULL,
      `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      `command` varchar(100) DEFAULT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `name` (`name`)
    )
    CREATE TABLE `todo` (
      `id` int(4) NOT NULL AUTO_INCREMENT,
      `task_name` varchar(30) NOT NULL,
      `submitted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `started` timestamp NULL DEFAULT NULL,
      `completed` timestamp NULL DEFAULT NULL,
      `deleted` timestamp NULL DEFAULT NULL,
      `submitter` varchar(30) DEFAULT NULL,
      `exit_status` smallint(6) DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) 

And some privileges. For the webpage:

    grant usage,select on cronbutton.tasks to cronbutton_web@localhost;
    grant usage,insert,select on cronbutton.todo to cronbutton_web@localhost;

For the runner: 

    grant usage,select on cronbutton.tasks to cronbutton_runner@localhost identified by 'supersecurepassword';
    grant usage,select,update,insert on cronbutton.todo to cronbutton_runner@localhost;

Then stick the PHP file somewhere it can be served, setting the MySQL password as a SetENV, and
ditto for the runner.

## Configure

Tasks are added on the MySQL shell. Neither of the above-defined users are able to modify these, so you
will need a third (root?). Something like:

    insert into tasks (name, description, command) values ('tmp clearout', 'Delete old files in /app/tmp', 'find /app/tmp -mtime +60 -delete');

perhaps. The command is run exactly as it appears in the table, there's no substitution going on, but it 
is run in  a `sh` subshell by Perl's `qx//` operator.

## Recover

If a task has aborted for any reason, it won't be marked as 'completed' and so you won't be able to
trigger any more of them. There's two responses to this:

* set the 'completed' field to any time at all and it'll show as having finished at that time
* set the 'deleted' field to any time at all and it'll not be considered any more

Nothing does any checking that 'completed' or 'deleted' happened after 'submitted' or 'started'
