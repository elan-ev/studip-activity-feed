<?
/*
 * Copyright (c) 2012  <mlunzena@uos.de>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 */

interface ActivityProvider
{
    /**
     * Get all activities for this user as an array.
     */
    function getActivities($user_id, $range, $days);
}
