<div class="content-wrapper">
    <div id="bloque" class="content">
        <h1 id="token">TOKEN</h1>
        <h2 id="token_get">GET TOKEN</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td>http://api.mynuvola.net/oauth/token</td>
                </tr>
                <tr>
                    <td class="bold">Method</td>
                    <td>POST</td>
                </tr>
                <tr>
                    <td class="bold">Data</td>
                    <td><per id="json-token_get-data"></per></td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td><per id="json-token_get-response"></per></td>
                </tr>
            </tbody>
        </table>


        <h2 id="token_refresh">REFRESH TOKEN</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td>http://api.mynuvola.net/oauth/token</td>
                </tr>
                <tr>
                    <td class="bold">Method</td>
                    <td>POST</td>
                </tr>
                <tr>
                    <td class="bold">Data</td>
                    <td><per id="json-token_refresh-data"></per></td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td><per id="json-token_refresh-response"></per></td>
                </tr>
            </tbody>
        </table>

        <h1 id="hotels">HOTELS</h1>
        <h2 id="hotels_get_list">GET HOTELS LIST</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">http://api.mynuvola.net/api/hotels</td>
                </tr>
                <tr>
                    <td class="bold">Method</td>
                    <td colspan="2">GET</td>
                </tr>
                <tr>
                    <td class="bold">Headers</td>
                    <td>Autorization</td>
                    <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="json-hotels_get_list-response"></per></td>
                </tr>
            </tbody>
        </table>
        <!-- ############################################################################
        ################################################################################# -->
        <h1 id="rooms">GET ROOMS</h1>
        <h2 id="rooms_get_list">GET ROOMS LIST</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">http://api.mynuvola.net/api/v1/hotel-room</td>
                </tr>
                <tr>
                    <td class="bold">Method</td>
                    <td colspan="2">GET</td>
                </tr>
                <tr>
                    <td class="bold" rowspan="2">Headers</td>
                    <td>Autorization</td>
                    <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
                </tr>
                <tr>
                    <td>Content-type</td>
                    <td>"application/json"</td>
                </tr>
                <tr>
                    <td class="bold">Data</td>
                    <td colspan="2"><code>?hotel_id={ numeric | required }&paginate={ numeric | optional }</code></td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per class="json-rooms_get_list-response"></per></td>
                </tr>
                <tr class="note">
                    <td class="bold"><i class="fa fa-info-circle" aria-hidden="true"></i></td>
                    <td colspan="2"><p>The information provided by this endpoint is paged, the parameter "paginate" is optional, its default value is 100, this means that it divides the total content among 100.</p></td>
                </tr>
            </tbody>
        </table>
        <h2 id="rooms_get_list_available">GET ROOMS LIST AVAILABLE</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">http://api.mynuvola.net/api/v1/hotel-room-available</td>
                </tr>
                <tr>
                    <td class="bold">Method</td>
                    <td colspan="2">GET</td>
                </tr>
                <tr>
                    <td class="bold" rowspan="2">Headers</td>
                    <td>Autorization</td>
                    <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
                </tr>
                <tr>
                    <td>Content-type</td>
                    <td>"application/json"</td>
                </tr>
                <tr>
                    <td class="bold">Data</td>
                    <td colspan="2"><code>?hotel_id={ numeric | required }&paginate={ numeric | optional }</code></td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per class="json-rooms_get_list-response"></per></td>
                </tr>
                <tr class="note">
                    <td class="bold"><i class="fa fa-info-circle" aria-hidden="true"></i></td>
                    <td colspan="2"><p>The information provided by this endpoint is paged, the parameter "paginate" is optional, its default value is 100, this means that it divides the total content among 100.</p></td>
                </tr>
            </tbody>
        </table>
        <h2 id="rooms_new">NEW ROOM</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">http://api.mynuvola.net/api/v1/hotel-room</td>
                </tr>
                <tr>
                    <td class="bold">Method</td>
                    <td colspan="2">POST</td>
                </tr>
                <tr>
                    <td class="bold" rowspan="2">Headers</td>
                    <td>Autorization</td>
                    <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
                </tr>
                <tr>
                    <td>Content-type</td>
                    <td>"application/json"</td>
                </tr>
                <tr>
                    <td class="bold">Data</td>
                    <td colspan="2"><per id="json-rooms_new-data"></per></td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="json-rooms_new-response"></per></td>
                </tr>
            </tbody>
        </table>
        <h2 id="rooms_update">UPDATE ROOM</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">http://api.mynuvola.net/api/v1/hotel-room/<code>{ room_id }</code></td>
                </tr>
                <tr>
                    <td class="bold">Method</td>
                    <td colspan="2">UPDATE</td>
                </tr>
                <tr>
                    <td class="bold" rowspan="2">Headers</td>
                    <td>Autorization</td>
                    <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
                </tr>
                <tr>
                    <td>Content-type</td>
                    <td>"application/json"</td>
                </tr>
                <tr>
                    <td class="bold">Data</td>
                    <td colspan="2"><per id="json-rooms_update-data"></per></td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="json-rooms_update-response"></per></td>
                </tr>
            </tbody>
        </table>
        <h2 id="rooms_delete">DELETE ROOM</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">http://api.mynuvola.net/api/v1/hotel-room/<code>{ room_id }</code></td>
                </tr>
                <tr>
                    <td class="bold">Method</td>
                    <td colspan="2">DELETE</td>
                </tr>
                <tr>
                    <td class="bold" rowspan="2">Headers</td>
                    <td>Autorization</td>
                    <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
                </tr>
                <tr>
                    <td>Content-type</td>
                    <td>"application/json"</td>
                </tr>
                <tr>
                    <td class="bold">Data</td>
                    <td colspan="2"><code>?hotel_id={ hotel_id | numeric }</code></td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="json-rooms_delete-response"></per></td>
                </tr>
            </tbody>
        </table>
        <!-- ############################################################################
        ################################################################################# -->
        <h1 id="guest">GUEST</h1>
        <h2 id="guest_get_list">GET GUEST LIST</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">http://api.mynuvola.net/api/v1/guest</td>
                </tr>
                <tr>
                    <td class="bold">Method</td>
                    <td colspan="2">GET</td>
                </tr>
                <tr>
                    <td class="bold" rowspan="2">Headers</td>
                    <td>Autorization</td>
                    <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
                </tr>
                <tr>
                    <td>Content-type</td>
                    <td>"application/json"</td>
                </tr>
                <tr>
                    <td class="bold">Data</td>
                    <td colspan="2"><code>?hotel_id={ numeric | required }&paginate={ numeric | optional }</code></td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="json-guest_get_list-response"></per></td>
                </tr>
                <tr class="note">
                    <td class="bold"><i class="fa fa-info-circle" aria-hidden="true"></i></td>
                    <td colspan="2"><p>The information provided by this endpoint is paged, the parameter "paginate" is optional, its default value is 100, this means that it divides the total content among 100.</p></td>
                </tr>
            </tbody>
        </table>
        <h2 id="guest_new">NEW GUEST</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">http://api.mynuvola.net/api/v1/guest</td>
                </tr>
                <tr>
                    <td class="bold">Method</td>
                    <td colspan="2">POST</td>
                </tr>
                <tr>
                    <td class="bold" rowspan="2">Headers</td>
                    <td>Autorization</td>
                    <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
                </tr>
                <tr>
                    <td>Content-type</td>
                    <td>"application/json"</td>
                </tr>
                <tr>
                    <td class="bold">Data</td>
                    <td colspan="2"><per id="json-new_guest-data"></per></td>
                </tr>
                <tr class="note">
                    <td class="bold"><i class="fa fa-info-circle" aria-hidden="true"></i></td>
                    <td colspan="2">
                        <ul>
                            <li><b>hotel_id:</b> this parameter refers to the hotel identifier where the operation will be performed <a href="#get_hotels">GET HOTELS</a>.</li>
                            <li><b>firstname</b> and <b>lastname:</b> the combination of these fields are unique in the hotel system.</li>
                            <li>
                                <b>email_address</b> and <b>phone_number:</b> this field is unique in the hotel system.
                                <ul>
                                    <li><a href="#validate_guest_s_email">Validate email</a> </li>
                                    <li><a href="#validate_guest_s_phone">Validate phone</a></li>
                                </ul>
                            </li>
                            <li><b>room_no:</b> this field refers to the room_id parameter obtained from the end point <a href="#rooms_get_list">GET ROOMS LIST</a>.</li>
                        </ul>
                    </td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="json-new_guest-response"></per></td>
                </tr>
            </tbody>
        </table>
        <h2 id="guest_update">UPDATE GUEST</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">http://api.mynuvola.net/api/v1/guest/<code>{ guest_id }</code></td>
                </tr>
                <tr>
                    <td class="bold">Method</td>
                    <td colspan="2">PUT</td>
                </tr>
                <tr>
                    <td class="bold" rowspan="2">Headers</td>
                    <td>Autorization</td>
                    <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
                </tr>
                <tr>
                    <td>Content-type</td>
                    <td>"application/json"</td>
                </tr>
                <tr>
                    <td class="bold">Data</td>
                    <td colspan="2"><per id="json-guest_update-data"></per></td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="json-guest_update-response"></per></td>
                </tr>
            </tbody>
        </table>
        <h2 id="guest_delete">DELETE GUEST</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">http://api.mynuvola.net/api/v1/guest/<code>{ guest_id }</code></td>
                </tr>
                <tr>
                    <td class="bold">Method</td>
                    <td colspan="2">DELETE</td>
                </tr>
                <tr>
                    <td class="bold" rowspan="2">Headers</td>
                    <td>Autorization</td>
                    <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
                </tr>
                <tr>
                    <td>Content-type</td>
                    <td>"application/json"</td>
                </tr>                            
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="json-guest_delete-response"></per></td>
                </tr>
            </tbody>
        </table>
        <h3 id="validate_guest_s_email">Validat email</h3>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">http://api.mynuvola.net/api/v1/guest/validate/email/<code>{ hotel_id }</code>/<code>{ email }</code></td>
                </tr>
                <tr>
                    <td class="bold">Method</td>
                    <td colspan="2">GET</td>
                </tr>
                <tr>
                    <td class="bold" rowspan="2">Headers</td>
                    <td>Autorization</td>
                    <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
                </tr>
                <tr>
                    <td>Content-type</td>
                    <td>"application/json"</td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="json-validate_guest_s_email-response"></per></td>
                </tr>
                <tr class="note">
                    <td class="bold"><i class="fa fa-info-circle" aria-hidden="true"></i></td>
                    <td colspan="2"><p>The variable "exists" returns null, this means that the information supplied (hotel_id) is not correct.</p></td>
                </tr>
            </tbody>
        </table>
        <h3 id="validate_guest_s_phone">Validate phone</h3>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">http://api.mynuvola.net/api/v1/guest/validate/phone/<code>{ hotel_id }</code>/<code>{ phone }</code></td>
                </tr>
                <tr>
                    <td class="bold">Method</td>
                    <td colspan="2">GET</td>
                </tr>
                <tr>
                    <td class="bold" rowspan="2">Headers</td>
                    <td>Autorization</td>
                    <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
                </tr>
                <tr>
                    <td>Content-type</td>
                    <td>"application/json"</td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="json-validate_guest_s_phone-response"></per></td>
                </tr>
                <tr class="note">
                    <td class="bold"><i class="fa fa-info-circle" aria-hidden="true"></i></td>
                    <td colspan="2"><p>The variable "exists" returns null, this means that the information supplied (hotel_id) is not correct.</p></td>
                </tr>
            </tbody>
        </table>
        <!-- ############################################################################
        ################################################################################# -->
        <h1 id="roles">ROLES</h1>
        <h2 id="roles_get_list">GET ROLES LIST</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">http://api.mynuvola.net/api/v1/role</td>
                </tr>
                <tr>
                    <td class="bold">Method</td>
                    <td colspan="2">GET</td>
                </tr>
                <tr>
                    <td class="bold" rowspan="2">Headers</td>
                    <td>Autorization</td>
                    <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
                </tr>
                <tr>
                    <td>Content-type</td>
                    <td>"application/json"</td>
                </tr>
                <tr>
                    <td class="bold">Data</td>
                    <td colspan="2"><code>?hotel_id={ numeric | required }</code></td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="json-get_roles-response"></per></td>
                </tr>
            </tbody>
        </table>
        <!-- ############################################################################
        ################################################################################# -->
        <h1 id="staff">STAFF</h1>
        <h2 id="staff_new">NEW STAFF</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">http://api.mynuvola.net/api/v1/staff</td>
                </tr>
                <tr>
                    <td class="bold">Method</td>
                    <td colspan="2">POST</td>
                </tr>
                <tr>
                    <td class="bold" rowspan="2">Headers</td>
                    <td>Autorization</td>
                    <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
                </tr>
                <tr>
                    <td>Content-type</td>
                    <td>"application/json"</td>
                </tr>
                <tr>
                    <td class="bold">Data</td>
                    <td colspan="2"><per id="json-new_staff-data"></per></td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="json-new_staff-response"></per></td>
                </tr>
            </tbody>
        </table>
        <h2 id="staff_info">GET STAFF INFORMATION</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">http://api.mynuvola.net/api/user</td>
                </tr>
                <tr>
                    <td class="bold">Method</td>
                    <td colspan="2">GET</td>
                </tr>
                <tr>
                    <td class="bold">Headers</td>
                    <td>Autorization</td>
                    <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="json-get_user-response"></per></td>
                </tr>
            </tbody>
        </table>
        <h1 id="dept_tags">DEPARTMENT AND TAGS</h1>
        <h2 id="dept_tags_get_list">GET DEPARTMENT AND TAGS LIST</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">http://api.mynuvola.net/api/v1/dept-tag</td>
                </tr>
                <tr>
                    <td class="bold">Method</td>
                    <td colspan="2">GET</td>
                </tr>
                <tr>
                    <td class="bold" rowspan="2">Headers</td>
                    <td>Autorization</td>
                    <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
                </tr>
                <tr>
                    <td>Content-type</td>
                    <td>"application/json"</td>
                </tr>
                <tr>
                    <td class="bold">Data</td>
                    <td colspan="2"><code>?hotel_id={ numeric | required }&paginate={ numeric | optional }</code></td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="json-get_dept_tags-response"></per></td>
                </tr>
                <tr class="note">
                    <td class="bold"><i class="fa fa-info-circle" aria-hidden="true"></i></td>
                    <td colspan="2"><p>The information provided by this endpoint is paged, the parameter "paginate" is optional, its default value is 100, this means that it divides the total content among 100.</p></td>
                </tr>
            </tbody>
        </table>
        <h1 id="event">EVENT</h1>
        <h2 id="event_new">NEW EVENT</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">http://api.mynuvola.net/api/v1/event</td>
                </tr>
                <tr>
                    <td class="bold">Method</td>
                    <td colspan="2">POST</td>
                </tr>
                <tr>
                    <td class="bold" rowspan="2">Headers</td>
                    <td>Autorization</td>
                    <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
                </tr>
                <tr>
                    <td>Content-type</td>
                    <td>"application/json"</td>
                </tr>
                <tr>
                    <td class="bold">Data</td>
                    <td colspan="2"><per id="json-new_event-data"><per></td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="json-new_event-response"></per></td>
                </tr>
            </tbody>
        </table>
        <!-- ############################################################################
        ################################################################################# -->
        <h1 id="package">PACKAGES</h1>
        <h2 id="package_get_list">GET PACKAGES LIST</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">http://api.mynuvola.net/api/v1/package</td>
                </tr>
                <tr>
                    <td class="bold">Method</td>
                    <td colspan="2">GET</td>
                </tr>
                <tr>
                    <td class="bold" rowspan="2">Headers</td>
                    <td>Autorization</td>
                    <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
                </tr>
                <tr>
                    <td>Content-type</td>
                    <td>"application/json"</td>
                </tr>
                <tr>
                    <td class="bold">Data</td>
                    <td colspan="2"><code>?hotel_id={ numeric | required }&paginate={ numeric | optional }</code></td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="json-package-response"></per></td>
                </tr>
                <tr class="note">
                    <td class="bold"><i class="fa fa-info-circle" aria-hidden="true"></i></td>
                    <td colspan="2"><p>The information provided by this endpoint is paged, the parameter "paginate" is optional, its default value is 100, this means that it divides the total content among 100.</p></td>
                </tr>
            </tbody>
        </table>
        <h2 id="package_new">NEW PACKAGE</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">http://api.mynuvola.net/api/v1/package</td>
                </tr>
                <tr>
                    <td class="bold">Method</td>
                    <td colspan="2">POST</td>
                </tr>
                <tr>
                    <td class="bold" rowspan="2">Headers</td>
                    <td>Autorization</td>
                    <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
                </tr>
                <tr>
                    <td>Content-type</td>
                    <td>"application/json"</td>
                </tr>
                <tr>
                    <td class="bold">Data_option_1</td>
                    <td colspan="2"><per id="json-package_new_1-data"></per></td>
                </tr>
                <tr>
                    <td class="bold">Data_option_2</td>
                    <td colspan="2"><per id="json-package_new_2-data"></per></td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="json-package_new-response"></per></td>
                </tr>
            </tbody>
        </table>
        <h2 id="package_update">UPDATE PACKAGES</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">http://api.mynuvola.net/api/v1/package/<code>{ pkg_no }</code></td>
                </tr>
                <tr>
                    <td class="bold">Method</td>
                    <td colspan="2">PUT</td>
                </tr>
                <tr>
                    <td class="bold" rowspan="2">Headers</td>
                    <td>Autorization</td>
                    <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
                </tr>
                <tr>
                    <td>Content-type</td>
                    <td>"application/json"</td>
                </tr>
                <tr>
                    <td class="bold">Data</td>
                    <td colspan="2"><per id="json-package_update-data"></per></td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="json-package_update-response"></per></td>
                </tr>
            </tbody>
        </table>
        <h2 id="package_delete">DELETE PACKAGES</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">http://api.mynuvola.net/api/v1/package/<code>{ pkg_no }</code></td>
                </tr>
                <tr>
                    <td class="bold">Method</td>
                    <td colspan="2">DELETE</td>
                </tr>
                <tr>
                    <td class="bold" rowspan="2">Headers</td>
                    <td>Autorization</td>
                    <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
                </tr>
                <tr>
                    <td>Content-type</td>
                    <td>"application/json"</td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="json-package_delete-response"></per></td>
                </tr>
            </tbody>
        </table>
        <!-- ############################################################################
        ################################################################################# -->
        <h1 id="lost_found">LOST FOUND</h1>
        <h2 id="lost_found_get_list">GET LOST FOUND LIST</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">http://api.mynuvola.net/api/v1/lost-found</td>
                </tr>
                <tr>
                    <td class="bold">Method</td>
                    <td colspan="2">GET</td>
                </tr>
                <tr>
                    <td class="bold" rowspan="2">Headers</td>
                    <td>Autorization</td>
                    <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
                </tr>
                <tr>
                    <td>Content-type</td>
                    <td>"application/json"</td>
                </tr>
                <tr>
                    <td class="bold">Data</td>
                    <td colspan="2"><code>?hotel_id={ numeric | required }&paginate={ numeric | optional }</code></td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="json-lost_found_get-response"></per></td>
                </tr>
                <tr class="note">
                    <td class="bold"><i class="fa fa-info-circle" aria-hidden="true"></i></td>
                    <td colspan="2"><p>The information provided by this endpoint is paged, the parameter "paginate" is optional, its default value is 100, this means that it divides the total content among 100.</p></td>
                </tr>
            </tbody>
        </table>
        <h2 id="lost_found_new">NEW LOST FOUND</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">http://api.mynuvola.net/api/v1/lost-found</td>
                </tr>
                <tr>
                    <td class="bold">Method</td>
                    <td colspan="2">POST</td>
                </tr>
                <tr>
                    <td class="bold" rowspan="2">Headers</td>
                    <td>Autorization</td>
                    <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
                </tr>
                <tr>
                    <td>Content-type</td>
                    <td>"application/json"</td>
                </tr>
                <tr>
                    <td class="bold">Data_option_1</td>
                    <td colspan="2"><per id="json-lost_found_new_1-data"></per></td>
                </tr>
                <tr>
                    <td class="bold">Data_option_2</td>
                    <td colspan="2"><per id="json-lost_found_new_2-data"></per></td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="json-lost_found_new-response"></per></td>
                </tr>
            </tbody>
        </table>
        <h2 id="lost_found_update">UPDATE LOST FOUND</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">http://api.mynuvola.net/api/v1/lost-found/<code>{ lst_fnd_no }</code></td>
                </tr>
                <tr>
                    <td class="bold">Method</td>
                    <td colspan="2">PUT</td>
                </tr>
                <tr>
                    <td class="bold" rowspan="2">Headers</td>
                    <td>Autorization</td>
                    <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
                </tr>
                <tr>
                    <td>Content-type</td>
                    <td>"application/json"</td>
                </tr>
                <tr>
                    <td class="bold">Data</td>
                    <td colspan="2"><per id="json-lost_found_update-data"></per></td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="json-lost_found_update-response"></per></td>
                </tr>
            </tbody>
        </table>
        <h2 id="lost_found_delete">DELETE LOST FOUND</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">http://api.mynuvola.net/api/v1/lost-found/<code>{ lst_fnd_no }</code></td>
                </tr>
                <tr>
                    <td class="bold">Method</td>
                    <td colspan="2">DELETE</td>
                </tr>
                <tr>
                    <td class="bold" rowspan="2">Headers</td>
                    <td>Autorization</td>
                    <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
                </tr>
                <tr>
                    <td>Content-type</td>
                    <td>"application/json"</td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="json-lost_found_delete-response"></per></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>