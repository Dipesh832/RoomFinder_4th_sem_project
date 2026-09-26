<?php
/*
 * Shared tenant room card grid.
 *
 * Used by both Browse Rooms (tenant/rooms.php) and Saved Rooms
 * (tenant/saved.php) so the two listings cannot drift apart.
 *
 * Required from the including page:
 *   $rooms          array of room rows (id, title, description, location,
 *                   price, room_type, facilities, image, status,
 *                   max_occupants)
 *   $savedRoomIds    array keyed by (int) room id => true
 *   $pendingRoomIds  array keyed by (int) room id => true
 *
 * Optional:
 *   $roomCardFavRedirect  'rooms' | 'saved', the page the heart returns to
 *                         after a save/unsave. Defaults to 'rooms'.
 *   $roomCardFavFields    name => value map of extra hidden fields carried
 *                         through the save/unsave post. Defaults to [].
 */
$roomCardFavRedirect = $roomCardFavRedirect ?? 'rooms';
$roomCardFavFields = $roomCardFavFields ?? [];
?>
                <div class="my-rooms-grid">

                    <?php foreach ($rooms as $room): ?>

                        <article class="room-card">

                            <div class="room-card-media">

                                <?php if (!empty($room['image'])): ?>

                                    <img
                                        src="<?= htmlspecialchars(base_url($room['image'])) ?>"
                                        alt="<?= htmlspecialchars($room['title']) ?>"
                                        class="room-card-image"
                                    >

                                <?php else: ?>

                                    <div class="room-card-image room-card-placeholder">
                                        No Image
                                    </div>

                                <?php endif; ?>

                                <?php $isRoomSaved = isset($savedRoomIds[(int) $room['id']]); ?>

                                <div class="room-card-media-bar">

                                    <?php /*
                                     * The badge reflects the room's real status.
                                     * Browse Rooms only ever selects available
                                     * rooms, so it always renders "Available"
                                     * here; Saved Rooms can include a room that
                                     * was booked after it was saved.
                                     */ ?>
                                    <?php $isRoomAvailable = $room['status'] === 'available'; ?>
                                    <span class="room-status <?= $isRoomAvailable ? 'available' : 'booked' ?>">
                                        <?= htmlspecialchars(ucfirst($room['status'])) ?>
                                    </span>

                                    <form action="bookmark-room.php" method="POST" class="room-card-fav-form">
                                        <input type="hidden" name="room_id" value="<?= (int) $room['id'] ?>">
                                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($roomCardFavRedirect) ?>">
                                        <?php /*
                                         * Browse Rooms repeats its active filters
                                         * here so the tenant lands back on the same
                                         * result list. The tags are indented with
                                         * the input on purpose: it keeps the
                                         * rendered markup byte-for-byte identical
                                         * to the previous inline block.
                                         */ ?>
                                        <?php foreach ($roomCardFavFields as $favFieldName => $favFieldValue): ?>
                                        <input type="hidden" name="<?= htmlspecialchars($favFieldName) ?>"
                                            value="<?= htmlspecialchars((string) $favFieldValue) ?>">
                                        <?php endforeach; ?>
                                        <?= csrf_field() ?>
                                        <button type="submit"
                                            class="room-card-fav-btn <?= $isRoomSaved ? 'is-saved' : '' ?>"
                                            aria-pressed="<?= $isRoomSaved ? 'true' : 'false' ?>"
                                            aria-label="<?= $isRoomSaved ? 'Remove saved room' : 'Save room' ?>"
                                            title="<?= $isRoomSaved ? 'Remove saved room' : 'Save room' ?>">
                                            <?php /*
                                             * One heart geometry for both states: the same path is
                                             * filled when saved and left open when not, so the
                                             * icon never changes shape. currentColor keeps the
                                             * heart red on the white button and white on the
                                             * red one. The button carries the accessible
                                             * label, so the SVG itself is hidden from
                                             * assistive technology.
                                             */ ?>
                                            <svg class="room-card-fav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <path
                                                    d="M12 21.4C10.9 20.3 2.1 14.9 2.1 9C2.1 5.5 4.7 2.8 7.9 2.8C9.8 2.8 11.3 3.8 12 5.2C12.7 3.8 14.2 2.8 16.1 2.8C19.3 2.8 21.9 5.5 21.9 9C21.9 14.9 13.1 20.3 12 21.4Z"
                                                    fill="<?= $isRoomSaved ? 'currentColor' : 'none' ?>"
                                                    stroke="currentColor"
                                                    stroke-width="2.2"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                />
                                            </svg>
                                        </button>
                                    </form>

                                </div>

                            </div>


                            <div class="room-card-content">

                                <div class="room-card-top">

                                    <h2 class="room-card-title">
                                        <?= htmlspecialchars($room['title']) ?>
                                    </h2>

                                </div>

                                <p class="room-card-location">
                                    <?= htmlspecialchars($room['location']) ?>
                                </p>

                                <div class="room-card-price">
                                    Rs. <?= number_format((float) $room['price'], 2) ?>
                                    <span>/ month</span>
                                </div>

                                <p class="room-card-type">
                                    <?= htmlspecialchars($room['room_type']) ?>
                                </p>

                                <p class="room-card-max-occupants">
                                    Maximum occupants: <?= (int) $room['max_occupants'] ?>
                                </p>

                                <?php
                                $tenantFacilitiesItems = array_values(
                                    array_filter(
                                        array_map('trim', preg_split('/[,|]/', $room['facilities'] ?? ''))
                                    )
                                );
                                ?>
                                <?php if (!empty($tenantFacilitiesItems)): ?>
                                    <p class="room-card-facilities">
                                        <?= htmlspecialchars(implode(' • ', $tenantFacilitiesItems)) ?>
                                    </p>
                                <?php endif; ?>

                                <p class="room-card-description">
                                    <?= htmlspecialchars($room['description']) ?>
                                </p>

                                <div class="room-card-actions">
                                    <a href="view-room.php?id=<?= (int) $room['id'] ?>" class="room-card-btn room-card-btn-view">
                                        View Details
                                    </a>
                                    <?php if (isset($pendingRoomIds[(int) $room['id']])): ?>
                                        <div class="room-card-btn room-card-btn-pending" role="status">
                                            Booking Requested
                                        </div>
                                    <?php elseif ($isRoomAvailable): ?>
                                        <a href="view-room.php?id=<?= (int) $room['id'] ?>#booking-form" class="room-card-btn room-card-btn-booking">
                                            Request Booking
                                        </a>
                                    <?php endif; ?>
                                </div>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>
