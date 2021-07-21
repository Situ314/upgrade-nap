@php ($i = 1)


<div class="sidebar perfect-scrollbar">
    <ul id="nav">
        @if($version == 'v1')
            <li>
                <a class="token" href="{{ url("/doc/$version/token") }}#token">{{$i}}. Token </a>
                <ul>
                    <li><a class="token_get"       href="{{ url("/doc/$version/token") }}#token_get">{{$i}}.1 Get token</a></li>
                    <li><a class="token_refresh"   href="{{ url("/doc/$version/token") }}#token_refresh">{{$i++}}.2 Refresh token</a></li>
                </ul>
            </li>

            <li><div class="separator"></div></li>
            <li>
                <a class="hotels" href="{{ url("/doc/$version/hotel") }}#hotels">{{$i}}. Hotels</a>
                <ul>
                    <li><a class="hotels_get_list" href="{{ url("/doc/$version/hotel") }}#hotels_get_list">{{$i++}}.1 Get Hotels List</a></li>
                </ul>
            </li>

            <li><div class="separator"></div></li>
            <li>
                <a class="rooms" href="{{ url("/doc/$version/room") }}#rooms">{{$i}}. Rooms</a>
                <ul>
                    <li><a class="rooms_list"           href="{{ url("/doc/$version/room") }}#rooms_list">{{$i}}.1 Get Rooms List</a></li>
                    <li><a class="rooms_list_available" href="{{ url("/doc/$version/room") }}#rooms_list_available">{{$i}}.2 Get Rooms List (Available)</a></li>
                    <li><a class="rooms_new"            href="{{ url("/doc/$version/room") }}#rooms_new">{{$i}}.3 New Room</a></li>
                    <li><a class="rooms_update"         href="{{ url("/doc/$version/room") }}#rooms_update">{{$i}}.4 Update Room</a></li>
                    <li><a class="rooms_delete"         href="{{ url("/doc/$version/room") }}#rooms_delete">{{$i++}}.5 Delete Room</a></li>
                </ul>
            </li>

            <li><div class="separator"></div></li>
            <li>
                <a class="guest" href="{{ url("/doc/$version/guest") }}#guest">{{$i}}. Guest</a>
                <ul>
                    <li><a class="guest_list"              href="{{ url("/doc/$version/guest") }}#guest_list">      {{$i}}.1 Get Guest List</a></li>
                    <li><a class="guest_new"               href="{{ url("/doc/$version/guest") }}#guest_new">       {{$i}}.2 New Guest</a></li>
                    <li><a class="guest_update"            href="{{ url("/doc/$version/guest") }}#guest_update">    {{$i}}.3 Update Guest</a></li>
                    <li><a class="guest_delete"            href="{{ url("/doc/$version/guest") }}#guest_delete">    {{$i}}.4 Delete Guest</a></li>
                    <li><a class="validate_email"          href="{{ url("/doc/$version/guest") }}#validate_email">  {{$i}}.5 Validate Email</a></li>
                    <li><a class="validate_phone"          href="{{ url("/doc/$version/guest") }}#validate_phone">  {{$i}}.6 Validate Phone</a></li>
                    <li><a class="close_checkin"           href="{{ url("/doc/$version/guest") }}#close_checkin">   {{$i++}}.7 Finish a stay <b>Checked Out</b></a></li>
                </ul>
            </li>

            <li><div class="separator"></div></li>
            <li>
                <a class="roles" href="{{ url("/doc/$version/role") }}#roles">{{$i}}. Roles</a>
                <ul>
                    <li><a class="roles_list" href="{{ url("/doc/$version/role") }}#roles_list">{{$i++}}.1 Roles list</a></li>
                </ul>
            </li>

            <li><div class="separator"></div></li>
            <li>
                <a class="staff" href="{{ url("/doc/$version/staff") }}#staff">{{$i}}. Staff</a>
                <ul>
                    <li><a class="staff_list"   href="{{ url("/doc/$version/staff") }}#staff_list">{{$i}}.1 Get Staff List</a></li>
                    <li><a class="staff_new"   href="{{ url("/doc/$version/staff") }}#staff_new">{{$i}}.2 New Staff</a></li>
                    <li><a class="staff_info"  href="{{ url("/doc/$version/staff") }}#staff_info">{{$i++}}.3 Get Staff Information</a></li>
                </ul>
            </li>

            <li><div class="separator"></div></li>
            <li>
                <a class="dept_tags" href="{{ url("/doc/$version/dept_tags") }}#dept_tags">{{$i}}. Department & Tags</a>
                <ul>
                    <li><a class="dept_tags_get_list" href="{{ url("/doc/$version/dept_tags") }}#dept_tags_get_list">{{$i++}}.1 Get Departments & Tags list</a></li>
                </ul>
            </li>

            <li><div class="separator"></div></li>
            <li>
                <a class="event" href="{{ url("/doc/$version/event") }}#event">{{$i}}. Events</a>
                <ul>
                    <li><a class="event_list"      href="{{ url("/doc/$version/event") }}#event_list">  {{$i}}.1 Get Events list</a></li>
                    <li><a class="event_new"       href="{{ url("/doc/$version/event") }}#event_new">   {{$i}}.2 New Event</a></li>
                    <li><a class="event_update"    href="{{ url("/doc/$version/event") }}#event_update">{{$i}}.3 Update Event</a></li>
                    <li><a class="event_delete"    href="{{ url("/doc/$version/event") }}#event_delete">{{$i++}}.4 Delete Event</a></li>
                </ul>
            </li>

            <li><div class="separator"></div></li>
            <li>
                <a class="package" href="{{ url("/doc/$version/package") }}#package">{{$i}}. Packages</a>
                <ul>
                    <li><a class="package_list"    href="{{ url("/doc/$version/package") }}#package_list">{{$i}}.1 Get packages list</a></li>
                    <li><a class="package_new"     href="{{ url("/doc/$version/package") }}#package_new">{{$i}}.2 New packages</a></li>
                    <li><a class="package_update"  href="{{ url("/doc/$version/package") }}#package_update">{{$i}}.3 Update packages</a></li>
                    <li><a class="package_delete"  href="{{ url("/doc/$version/package") }}#package_delete">{{$i++}}.4 Delete packages</a></li>
                </ul>
            </li>

            <li><div class="separator"></div></li>
            <li>
                <a class="lost_found" href="{{ url("/doc/$version/lost_found") }}#lost_found">{{$i}}. Lost & Found</a>
                <ul>
                    <li><a class="lost_found_list"     href="{{ url("/doc/$version/lost_found") }}#lost_found_list">{{$i}}.1 Get Lost & Found List</a></li>
                    <li><a class="lost_found_new"      href="{{ url("/doc/$version/lost_found") }}#lost_found_new" >{{$i}}.2 New Lost & Found</a></li>
                    <li><a class="lost_found_update"   href="{{ url("/doc/$version/lost_found") }}#lost_found_update">{{$i}}.3 Update Lost & Found</a></li>
                    <li><a class="lost_found_delete"   href="{{ url("/doc/$version/lost_found") }}#lost_found_delete">{{$i++}}.4 Delete Lost & Found</a></li>
                </ul>
            </li>

            

        @elseif($version == 'v2')
            <li>
                <a class="token" href="{{ url("/doc/$version/token") }}#token">{{$i}}. Token </a>
                <ul>
                    <li><a class="token_get"       href="{{ url("/doc/$version/token") }}#token_get">{{$i}}.1 Get token</a></li>
                    <li><a class="token_refresh"   href="{{ url("/doc/$version/token") }}#token_refresh">{{$i++}}.2 Refresh token</a></li>
                </ul>
            </li>
            <li>
                <div class="separator"></div>
                <a class="hotels" href="{{ url("/doc/$version/hotel") }}#hotels">{{$i}}. Hotels</a>
                <ul>
                    <li><a class="hotels_get_list" href="{{ url("/doc/$version/hotel") }}#hotels_get_list">{{$i++}}.1 Get Hotels List</a></li>
                </ul>
            </li>
            <li>
                <div class="separator"></div>
                <a class="rooms" href="{{ url("/doc/$version/room") }}#rooms">{{$i}}. Rooms</a>
                <ul>
                    <li><a class="rooms_list"           href="{{ url("/doc/$version/room") }}#rooms_list">{{$i}}.1 Get rooms list</a></li>
                    <li><a class="rooms_new"            href="{{ url("/doc/$version/room") }}#rooms_new">{{$i}}.3 New room</a></li>
                    <li><a class="rooms_update"         href="{{ url("/doc/$version/room") }}#rooms_update">{{$i}}.4 Update room</a></li>
                    <li><a class="rooms_delete"         href="{{ url("/doc/$version/room") }}#rooms_delete">{{$i++}}.5 Delete room</a></li>
                </ul>
            </li>
            <li>
                <div class="separator"></div>
                <a class="guest" href="{{ url("/doc/$version/guest") }}#guest">{{$i}}. Guest</a>
                <ul>
                    <li><a class="guest_list"      href="{{ url("/doc/$version/guest") }}#guest_list">{{$i}}.1 Guest list</a></li>
                    <li><a class="guest_new"       href="{{ url("/doc/$version/guest") }}#guest_new">{{$i}}.2 New guest</a></li>
                    <li><a class="guest_update"    href="{{ url("/doc/$version/guest") }}#guest_update">{{$i}}.3 Update guest</a></li>
                    <li><a class="guest_delete"    href="{{ url("/doc/$version/guest") }}#guest_delete">{{$i}}.4 Delete guest</a></li>
                    <li><a class="validate_email"  href="{{ url("/doc/$version/guest") }}#validate_email">{{$i}}.5 Validate email</a></li>
                    <li><a class="validate_phone"  href="{{ url("/doc/$version/guest") }}#validate_phone">{{$i}}.6 Validate phone</a></li>
                    <li><a class="close_checkin"   href="{{ url("/doc/$version/guest") }}#close_checkin">{{$i++}}.7 Finish a stay <b>Checked Out</b></a></li>
                </ul>
            </li>
            <li>
                <div class="separator"></div>
                <a class="roles" href="{{ url("/doc/$version/role") }}#roles">{{$i}}. Roles</a>
                <ul>
                    <li><a class="roles_list" href="{{ url("/doc/$version/role") }}#roles_list">{{$i++}}.1 Roles list</a></li>
                </ul>
            </li>            
            <li>
                <div class="separator"></div>
                <a class="staff" href="{{ url("/doc/$version/staff") }}#staff">{{$i}}. Staff</a>
                <ul>
                    <li><a class="staff_list"   href="{{ url("/doc/$version/staff") }}#staff_list">{{$i}}.1 Get Staff List</a></li>
                    <li><a class="staff_new"   href="{{ url("/doc/$version/staff") }}#staff_new">{{$i}}.2 New Staff</a></li>
                    <li><a class="staff_info"  href="{{ url("/doc/$version/staff") }}#staff_info">{{$i++}}.3 Get Staff Information</a></li>
                </ul>
            </li>
            <li>
                <div class="separator"></div>
                <a class="dept" href="{{ url("/doc/$version/dept") }}#dept">{{$i}}. Departments & Tags</a>
                <ul>
                    <li><a class="dept_list"    href="{{ url("/doc/$version/dept") }}#dept_list">{{$i}}.1 Get Departments & Tags list</a></li>
                    <li><a class="dept_by_id"   href="{{ url("/doc/$version/dept") }}#dept_by_id">{{$i++}}.2 Get Department by ID</a></li>
                </ul>
            </li>
            <li>
                <div class="separator"></div>
                <a class="event" href="{{ url("/doc/$version/event") }}#event">{{$i}}. Events</a>
                <ul>
                    <li><a class="event_list"     href="{{ url("/doc/$version/event") }}#event_list">{{$i}}.1 Get Events list</a></li>                    
                    <li><a class="new_event"      href="{{ url("/doc/$version/event") }}#event_new">{{$i}}.2 New Event</a></li>
                    <li><a class="new_event"      href="{{ url("/doc/$version/event") }}#event_update">{{$i}}.3 Update Event</a></li>
                    <li><a class="new_event"      href="{{ url("/doc/$version/event") }}#event_delete">{{$i++}}.4 Delete Event</a></li>
                    {{-- <li><a class="event_list_by_guest_id"      href="{{ url("/doc/$version/event") }}#event_list_by_guest_id">{{$i}}.2 Get Event List by Guest ID</a></li> --}}
                </ul>
            </li>
            <li>
            <div class="separator"></div>
                <a class="hsk" href="{{ url("/doc/$version/hsk") }}#hsk">{{$i}}. Housekeeping</a>
                <ul>
                    <li><a class="hsk_list"     href="{{ url("/doc/$version/hsk") }}#hsk_list">                     {{$i}}.1 Housekeeping Status List</a></li>
                    <li><a class="hsk_by_cleaning_id"     href="{{ url("/doc/$version/hsk") }}#hsk_by_cleaning_id"> {{$i}}.2 Housekeeping Status by Cleaning ID</a></li>
                    <li><a class="hsk_update"   href="{{ url("/doc/$version/hsk") }}#hsk_update">                   {{$i}}.3 Update Housekeeping Status</a></li>
                    <li><a class="pick_up"   href="{{ url("/doc/$version/hsk") }}#pick_up">                         {{$i++}}.4 Pickup Room</a></li>
                </ul>
            </li>
            <li>
                <div class="separator"></div>
                <a class="maintenance" href="{{ url("/doc/$version/maintenance") }}#maintenance">{{$i}}. Maintenance</a>
                <ul>
                    <li><a class="maintenance_list" href="{{ url("/doc/$version/hsk") }}#maintenance_list">{{$i++}}.1 Get Maintenance List</a></li>
                </ul>
            </li>
        @endif
    </ul>
</div>