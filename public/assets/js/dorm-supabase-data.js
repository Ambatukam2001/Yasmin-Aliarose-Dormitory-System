/**
 * All Supabase reads/writes for the dormitory app (rooms, beds, bookings).
 * Depends on: supabase-config.js + supabase.js (window.dormSupabase).
 */
(function (global) {
  'use strict';

  async function sb() {
    await global.supabaseReady;
    return global.dormSupabase;
  }

  function parseBedIdFromService(service) {
    var m = /^\[bed:(\d+)\]\s*/.exec(service || '');
    return m ? parseInt(m[1], 10) : null;
  }

  function extractReceiptUrl(service) {
    var s = service || '';
    var i = s.indexOf('receipt:');
    if (i === -1) return '';
    return s.slice(i + 'receipt:'.length).trim().split(/\s·/)[0].trim();
  }

  function parseServiceDetails(service) {
    var s = service || '';
    var contact = '';
    var cm = s.match(/Contact:\s*([^·]+)/);
    if (cm) contact = cm[1].trim();
    var g = s.match(/Guardian:\s*([^(·]+)\s*\(([^)]+)\)/);
    var guardian_name = g ? g[1].trim() : '';
    var guardian_contact = g ? g[2].trim() : '';
    var cat = 'College';
    if (/\bReviewer\b/.test(s)) cat = 'Reviewer';
    else if (/\bHigh School\b/.test(s)) cat = 'High School';
    else if (/\bCollege\b/.test(s)) cat = 'College';
    var sm = s.match(/School:\s*([^·]+)/);
    var school_name = sm ? sm[1].trim() : '';
    var rm = s.match(/Room\s+([^·]+?)(?=\s*·|\s*$)/);
    var room_no = rm ? rm[1].trim() : '';
    var fm = s.match(/Floor\s+(\d+)/);
    var floor_no = fm ? parseInt(fm[1], 10) : null;
    return {
      contact: contact,
      guardian_name: guardian_name,
      guardian_contact: guardian_contact,
      category: cat,
      school_name: school_name,
      room_no: room_no,
      floor_no: floor_no,
    };
  }

  function extractLastDueFromService(service) {
    var re = /due:(\d{4}-\d{2}-\d{2})/g;
    var m;
    var last = '';
    while ((m = re.exec(service || '')) !== null) last = m[1];
    return last;
  }

  function mapPaymentStatus(st) {
    var s = String(st || '').toLowerCase();
    if (s === 'pending') return 'Pending';
    if (s === 'active') return 'Confirmed';
    if (s === 'declined') return 'Declined';
    if (s === 'completed') return 'Cleared';
    if (s === 'archived') return 'Archived';
    return 'Pending';
  }

  function mapBookingStatus(st) {
    var s = String(st || '').toLowerCase();
    if (s === 'pending') return 'Pending';
    if (s === 'active') return 'Active';
    if (s === 'declined') return 'Cancelled';
    if (s === 'completed') return 'Completed';
    if (s === 'archived') return 'Archived';
    return 'Pending';
  }

  function normalizeBookingRow(b) {
    var bid = parseBedIdFromService(b.service);
    var meta = parseServiceDetails(b.service);
    var dueExtra = extractLastDueFromService(b.service);
    return {
      id: b.id,
      full_name: b.name,
      name: b.name,
      service: b.service,
      date: b.date,
      status: b.status,
      booking_ref: 'BK-' + b.id,
      category: meta.category,
      contact_number: meta.contact,
      guardian_name: meta.guardian_name,
      guardian_contact: meta.guardian_contact,
      school_name: meta.school_name,
      floor_no: meta.floor_no,
      room_no: meta.room_no,
      bed_id: bid,
      payment_status: mapPaymentStatus(b.status),
      booking_status: mapBookingStatus(b.status),
      monthly_rent: 1600,
      due_date: dueExtra || b.date,
      receipt_path: extractReceiptUrl(b.service),
    };
  }

  async function syncRoomFullness(roomId) {
    var client = await sb();
    var roomId = parseInt(roomId, 10);
    var res = await client.from('beds').select('status').eq('room_id', roomId);
    if (res.error) throw res.error;
    var beds = res.data || [];
    var total = beds.length;
    var occupied = beds.filter(function (x) {
      return x.status === 'Occupied';
    }).length;
    var vacancies = total - occupied;
    var isFull = total > 0 && vacancies <= 0;
    var u = await client
      .from('rooms')
      .update({ status: isFull ? 'Full' : 'Available' })
      .eq('id', roomId);
    if (u.error) throw u.error;
  }

  var DormSupabaseData = {
    parseBedIdFromService: parseBedIdFromService,
    parseServiceDetails: parseServiceDetails,

    buildBookingService: function (opts) {
      var bedId = opts.bedId;
      var roomNo = opts.roomNo;
      var floorNo = opts.floorNo;
      var category = opts.category || '';
      var school = opts.school || '';
      var contact = opts.contact || '';
      var guardian = opts.guardian || '';
      var guardianContact = opts.guardianContact || '';
      var paymentMethod = opts.paymentMethod || '';
      var parts = [
        '[bed:' + bedId + ']',
        'Room ' + roomNo,
        'Floor ' + floorNo,
        category,
        school ? 'School: ' + school : null,
        'Contact: ' + contact,
        'Guardian: ' + guardian + ' (' + guardianContact + ')',
        'Payment: ' + paymentMethod,
      ].filter(Boolean);
      return parts.join(' · ');
    },

    appendReceiptToService: function (service, url) {
      if (!url) return service;
      return (service || '').trim() + ' · receipt:' + url;
    },

    appendRentNote: function (service, amount, nextDue) {
      return (
        (service || '').trim() +
        ' · rent:' +
        String(amount) +
        ' due:' +
        String(nextDue)
      );
    },

    uploadReceiptIfAny: async function (file) {
      if (!file) return null;
      var client = await sb();
      var path =
        'public/' +
        Date.now() +
        '_' +
        String(file.name || 'file').replace(/[^a-z0-9._-]/gi, '_');
      path = path.slice(0, 200);
      var up = await client.storage.from('receipts').upload(path, file, {
        cacheControl: '3600',
        upsert: false,
      });
      if (up.error) {
        console.warn('[Supabase storage]', up.error.message);
        return null;
      }
      var pub = client.storage.from('receipts').getPublicUrl(path);
      return pub.data.publicUrl;
    },

    fetchFloorRooms: async function (floorNo) {
      var client = await sb();
      var res = await client
        .from('rooms')
        .select('id, room_no, floor_no, capacity, status, beds(id,status)')
        .eq('floor_no', floorNo)
        .order('room_no');
      var rows = res.data;
      if (res.error) {
        var res2 = await client
          .from('rooms')
          .select('id, room_no, floor_no, capacity, status')
          .eq('floor_no', floorNo)
          .order('room_no');
        if (res2.error) throw res2.error;
        rows = res2.data || [];
        for (var i = 0; i < rows.length; i++) {
          var br = await client.from('beds').select('id,status').eq('room_id', rows[i].id);
          if (br.error) throw br.error;
          rows[i].beds = br.data || [];
        }
      }
      return (rows || []).map(function (r) {
        var beds = r.beds || [];
        var total_beds = beds.length;
        var occupied_count = beds.filter(function (b) {
          return b.status === 'Occupied';
        }).length;
        var reserved_count = beds.filter(function (b) {
          return b.status === 'Reserved';
        }).length;
        return {
          id: r.id,
          room_no: r.room_no,
          floor_no: r.floor_no,
          capacity: r.capacity,
          status: r.status,
          created_at: r.created_at,
          total_beds: total_beds,
          occupied_count: occupied_count,
          reserved_count: reserved_count,
        };
      });
    },

    fetchRoomBeds: async function (roomId) {
      var client = await sb();
      var res = await client
        .from('beds')
        .select('id, room_id, floor_id, bed_no, status, reserved_at, created_at')
        .eq('room_id', roomId)
        .order('bed_no');
      if (res.error) throw res.error;
      return res.data || [];
    },

    insertBooking: async function (row) {
      var client = await sb();
      var res = await client
        .from('bookings')
        .insert({
          name: row.name,
          service: row.service,
          date: row.date,
          status: row.status || 'pending',
        })
        .select('id')
        .single();
      if (res.error) throw res.error;
      return res.data;
    },

    updateBed: async function (bedId, fields) {
      var client = await sb();
      var res = await client.from('beds').update(fields).eq('id', bedId);
      if (res.error) throw res.error;
      var b = await client.from('beds').select('room_id').eq('id', bedId).single();
      if (!b.error && b.data && b.data.room_id) {
        await syncRoomFullness(b.data.room_id);
      }
    },

    reserveBed: async function (bedId) {
      await DormSupabaseData.updateBed(bedId, {
        status: 'Reserved',
        reserved_at: new Date().toISOString(),
      });
    },

    setBedAvailable: async function (bedId) {
      await DormSupabaseData.updateBed(bedId, {
        status: 'Available',
        reserved_at: null,
      });
    },

    setBedOccupied: async function (bedId) {
      await DormSupabaseData.updateBed(bedId, {
        status: 'Occupied',
        reserved_at: new Date().toISOString(),
      });
    },

    fetchBookings: async function (filter) {
      var client = await sb();
      var q = client.from('bookings').select('*').order('id', { ascending: false });
      var f = filter === 'all' ? 'all' : filter;
      if (f === 'Pending') q = q.eq('status', 'pending');
      else if (f === 'Active') q = q.eq('status', 'active');
      else if (f === 'archive') q = q.eq('status', 'archived');
      else if (f === 'Overdue') {
        var today = new Date().toISOString().slice(0, 10);
        q = q.eq('status', 'active').lt('date', today);
      }
      var res = await q;
      if (res.error) throw res.error;
      return (res.data || []).map(normalizeBookingRow);
    },

    updateBookingStatus: async function (id, status) {
      var client = await sb();
      var res = await client.from('bookings').update({ status: status }).eq('id', id);
      if (res.error) throw res.error;
    },

    updateBookingService: async function (id, service) {
      var client = await sb();
      var res = await client.from('bookings').update({ service: service }).eq('id', id);
      if (res.error) throw res.error;
    },

    deleteBooking: async function (id) {
      var client = await sb();
      var res = await client.from('bookings').delete().eq('id', id);
      if (res.error) throw res.error;
    },

    recordRentPayment: async function (bookingId, amount, nextDue) {
      var client = await sb();
      var row = await client.from('bookings').select('service').eq('id', bookingId).single();
      if (row.error) throw row.error;
      var newService = DormSupabaseData.appendRentNote(row.data.service, amount, nextDue);
      var res = await client.from('bookings').update({ service: newService }).eq('id', bookingId);
      if (res.error) throw res.error;
    },

    acceptBooking: async function (bookingId) {
      var client = await sb();
      var row = await client.from('bookings').select('service').eq('id', bookingId).single();
      if (row.error) throw row.error;
      var bid = parseBedIdFromService(row.data.service);
      await client.from('bookings').update({ status: 'active' }).eq('id', bookingId);
      if (bid) await DormSupabaseData.setBedOccupied(bid);
    },

    declineBooking: async function (bookingId) {
      var client = await sb();
      var row = await client.from('bookings').select('service').eq('id', bookingId).single();
      if (row.error) throw row.error;
      var bid = parseBedIdFromService(row.data.service);
      await client.from('bookings').update({ status: 'declined' }).eq('id', bookingId);
      if (bid) await DormSupabaseData.setBedAvailable(bid);
    },

    checkoutBooking: async function (bookingId) {
      var client = await sb();
      var row = await client.from('bookings').select('service').eq('id', bookingId).single();
      if (row.error) throw row.error;
      var bid = parseBedIdFromService(row.data.service);
      await client.from('bookings').update({ status: 'completed' }).eq('id', bookingId);
      if (bid) await DormSupabaseData.setBedAvailable(bid);
    },

    archiveBooking: async function (bookingId) {
      var client = await sb();
      var row = await client.from('bookings').select('service,status').eq('id', bookingId).single();
      if (row.error) throw row.error;
      var bid = parseBedIdFromService(row.data.service);
      var st = String(row.data.status || '').toLowerCase();
      await client.from('bookings').update({ status: 'archived' }).eq('id', bookingId);
      if (bid && (st === 'pending' || st === 'active')) {
        await DormSupabaseData.setBedAvailable(bid);
      }
    },

    restoreArchivedBooking: async function (bookingId) {
      var client = await sb();
      var row = await client.from('bookings').select('service,status').eq('id', bookingId).single();
      if (row.error) throw row.error;
      if (String(row.data.status || '').toLowerCase() !== 'archived') {
        throw new Error('Only archived records can be restored.');
      }
      var bid = parseBedIdFromService(row.data.service);
      if (bid) {
        var bed = await client.from('beds').select('status').eq('id', bid).single();
        if (bed.error) throw bed.error;
        if (bed.data.status !== 'Available') {
          throw new Error('The original bed is no longer available.');
        }
        await DormSupabaseData.reserveBed(bid);
      }
      await client.from('bookings').update({ status: 'pending' }).eq('id', bookingId);
    },

    fetchResidentsForDirectory: async function (tab) {
      var client = await sb();
      var q = client.from('bookings').select('*').order('id', { ascending: false });
      if (tab === 'active') q = q.eq('status', 'active');
      else if (tab === 'past') q = q.eq('status', 'completed');
      else if (tab === 'archive') q = q.eq('status', 'archived');
      var res = await q;
      if (res.error) throw res.error;
      return (res.data || []).map(function (b) {
        var n = normalizeBookingRow(b);
        return {
          id: n.id,
          full_name: n.full_name,
          contact_number: n.contact_number,
          guardian_name: n.guardian_name,
          guardian_contact: n.guardian_contact,
          category: n.category,
          school_name: n.school_name,
          booking_status: n.booking_status,
          payment_status: n.payment_status,
          due_date: n.due_date,
          created_at: b.date,
          deleted_at: tab === 'archive' ? b.date : null,
          service: b.service,
          booking_ref: n.booking_ref,
        };
      });
    },

    listRoomsForSelect: async function () {
      var client = await sb();
      var res = await client.from('rooms').select('id, room_no, floor_no').order('id', { ascending: true });
      if (res.error) throw res.error;
      return res.data || [];
    },

    updateResidentDetails: async function (bookingId, fields) {
      var client = await sb();
      var row = await client.from('bookings').select('service, name').eq('id', bookingId).single();
      if (row.error) throw row.error;
      var old = row.data.service || '';
      var bid = parseBedIdFromService(old);
      var meta = parseServiceDetails(old);
      var roomNo = fields.room_no != null ? String(fields.room_no) : meta.room_no || '?';
      var floorNo =
        fields.floor_no != null ? parseInt(fields.floor_no, 10) : meta.floor_no != null ? meta.floor_no : 2;
      var payM = old.match(/Payment:\s*([^·]+)/);
      var paymentMethod = payM ? payM[1].trim() : 'Manual';
      var newService = DormSupabaseData.buildBookingService({
        bedId: bid || 0,
        roomNo: roomNo,
        floorNo: floorNo,
        category: fields.category || meta.category,
        school: fields.school_name != null ? fields.school_name : meta.school_name,
        contact: fields.contact_number || meta.contact,
        guardian: fields.guardian_name || meta.guardian_name,
        guardianContact: fields.guardian_contact || meta.guardian_contact,
        paymentMethod: paymentMethod,
      });
      if (bid) {
        newService = newService.replace(/^\[bed:\d+\]/, '[bed:' + bid + ']');
      }
      var upd = await client
        .from('bookings')
        .update({
          name: fields.full_name || row.data.name,
          service: newService,
        })
        .eq('id', bookingId);
      if (upd.error) throw upd.error;
    },

    createManualResident: async function (form) {
      var client = await sb();
      var room = await client
        .from('rooms')
        .select('room_no, floor_no')
        .eq('id', form.room_id)
        .single();
      if (room.error) throw room.error;
      var bed = await client.from('beds').select('id, status').eq('id', form.bed_id).single();
      if (bed.error) throw bed.error;
      if (bed.data.status !== 'Available') {
        throw new Error('Selected bed is not available.');
      }
      var service = DormSupabaseData.buildBookingService({
        bedId: form.bed_id,
        roomNo: room.data.room_no,
        floorNo: room.data.floor_no,
        category: form.category,
        school: form.school_name || '',
        contact: form.contact_number,
        guardian: form.guardian_name,
        guardianContact: form.guardian_contact,
        paymentMethod: 'Manual (Admin)',
      });
      var today = new Date().toISOString().slice(0, 10);
      var ins = await client
        .from('bookings')
        .insert({
          name: form.full_name,
          service: service,
          date: today,
          status: 'active',
        })
        .select('id')
        .single();
      if (ins.error) throw ins.error;
      await DormSupabaseData.setBedOccupied(form.bed_id);
      return ins.data;
    },

    findBlockingBookingForBed: async function (bedId) {
      var client = await sb();
      var prefix = '[bed:' + bedId + ']';
      var res = await client
        .from('bookings')
        .select('id,status')
        .like('service', prefix + '%')
        .in('status', ['pending', 'active']);
      if (res.error) throw res.error;
      var rows = res.data || [];
      return rows.length ? rows[0] : null;
    },

    /** Admin: add room + beds */
    addRoomWithBeds: async function (roomNo, floorNo, bedsCount) {
      var client = await sb();
      var ins = await client
        .from('rooms')
        .insert({
          room_no: roomNo,
          floor_no: floorNo,
          capacity: bedsCount,
          status: 'Available',
        })
        .select('id')
        .single();
      if (ins.error) throw ins.error;
      var roomId = ins.data.id;
      for (var i = 1; i <= bedsCount; i++) {
        var b = await client.from('beds').insert({
          room_id: roomId,
          floor_id: floorNo,
          bed_no: i,
          status: 'Available',
        });
        if (b.error) throw b.error;
      }
      await syncRoomFullness(roomId);
    },

    deleteRoom: async function (roomId) {
      var client = await sb();
      var del = await client.from('rooms').delete().eq('id', roomId);
      if (del.error) throw del.error;
    },

    addBedToRoom: async function (roomId) {
      var client = await sb();
      var room = await client.from('rooms').select('floor_no').eq('id', roomId).single();
      if (room.error) throw room.error;
      var maxRes = await client
        .from('beds')
        .select('bed_no')
        .eq('room_id', roomId)
        .order('bed_no', { ascending: false })
        .limit(1);
      if (maxRes.error) throw maxRes.error;
      var next = 1;
      if (maxRes.data && maxRes.data.length) next = (maxRes.data[0].bed_no || 0) + 1;
      var ins = await client
        .from('beds')
        .insert({
          room_id: roomId,
          floor_id: room.data.floor_no,
          bed_no: next,
          status: 'Available',
        })
        .select('id')
        .single();
      if (ins.error) throw ins.error;
      var cap = await client.from('rooms').select('capacity').eq('id', roomId).single();
      if (!cap.error && cap.data) {
        await client
          .from('rooms')
          .update({ capacity: (cap.data.capacity || 0) + 1 })
          .eq('id', roomId);
      }
      await syncRoomFullness(roomId);
      return ins.data;
    },

    deleteBed: async function (bedId) {
      var client = await sb();
      var bed = await client.from('beds').select('id,status,room_id').eq('id', bedId).single();
      if (bed.error) throw bed.error;
      if (bed.data.status === 'Occupied') {
        throw new Error('Cannot delete an occupied bed.');
      }
      var block = await DormSupabaseData.findBlockingBookingForBed(bedId);
      if (block) throw new Error('This bed has an active booking. Cancel the booking first.');
      var del = await client.from('beds').delete().eq('id', bedId);
      if (del.error) throw del.error;
      var roomId = bed.data.room_id;
      var cap = await client.from('rooms').select('capacity').eq('id', roomId).single();
      if (!cap.error && cap.data) {
        await client
          .from('rooms')
          .update({ capacity: Math.max(0, (cap.data.capacity || 0) - 1) })
          .eq('id', roomId);
      }
      await syncRoomFullness(roomId);
    },

    toggleBedStatus: async function (bedId, newStatus) {
      var client = await sb();
      var patch = { status: newStatus };
      patch.reserved_at = newStatus === 'Available' ? null : new Date().toISOString();
      var res = await client.from('beds').update(patch).eq('id', bedId);
      if (res.error) throw res.error;
      var b = await client.from('beds').select('room_id').eq('id', bedId).single();
      if (!b.error && b.data) await syncRoomFullness(b.data.room_id);
    },

    getDashboardStats: async function () {
      var client = await sb();
      var roomsC = await client.from('rooms').select('*', { count: 'exact', head: true });
      if (roomsC.error) throw roomsC.error;
      var bedsRes = await client.from('beds').select('status');
      if (bedsRes.error) throw bedsRes.error;
      var beds = bedsRes.data || [];
      var available = beds.filter(function (b) {
        return b.status === 'Available';
      }).length;
      var pendingC = await client
        .from('bookings')
        .select('*', { count: 'exact', head: true })
        .eq('status', 'pending');
      if (pendingC.error) throw pendingC.error;
      return {
        rooms: roomsC.count || 0,
        availableBeds: available,
        revenue: '₱0',
        overdue: 0,
        pendingBookings: pendingC.count || 0,
      };
    },

    fetchRecentBookings: async function (limit) {
      var client = await sb();
      var res = await client
        .from('bookings')
        .select('*')
        .order('id', { ascending: false })
        .limit(limit || 5);
      if (res.error) throw res.error;
      return (res.data || []).map(normalizeBookingRow);
    },

    subscribeBookings: function (onChange) {
      return sb()
        .then(function (client) {
          return client
            .channel('bookings-changes')
            .on(
              'postgres_changes',
              { event: '*', schema: 'public', table: 'bookings' },
              function () {
                if (typeof onChange === 'function') onChange();
              }
            )
            .subscribe();
        })
        .catch(function (e) {
          console.warn('[Supabase realtime]', e && e.message ? e.message : e);
        });
    },
  };

  global.DormSupabaseData = DormSupabaseData;
})(typeof window !== 'undefined' ? window : globalThis);
