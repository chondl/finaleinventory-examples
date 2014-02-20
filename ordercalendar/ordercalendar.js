;(function() {

  angular
    .module('OrderCalendar', ['ui.calendar'])

    // Configure connection to host as a side-effect of instantiating object used to access services provided by host
    .factory('host', function() {
      return finaleapi
        .hostConnection({ title:'Order calendar', description:'Display calendar of sales and purchases.' })
        .addListener('show', function(screen, params) {
          this.scrollHeightWrite(2000)
          this.showReadyNotify()
        })
    })

    .controller('OrderController', function($timeout, $scope, host) {
      var results = {
            facility: {fields:['facilityUrl','facilityName']},
            partyGroup: {fields:['partyId','groupName']},
            order: {fields:['orderUrl','orderId','orderTypeId','orderDate','orderRoleList']}
          }

      // Once the host requests a screen be displayed, make resource requests on the host to fetch data required to display the calendar.  The 
      // resourceGET function fetches the same data as available in Finale's HTTP REST interface.  The second argument is a query object that
      // currently supports a single paramter, fields, that lists the fields to retrieve. The resourceGET function returns tabular data in the 
      // same manner as Finale's HTTP REST interface.
      // 
      host.addListener('show', function() {
        _(results).forEach(function(v,k) {
          host.resourceGET(k, {fields:v.fields}, function(err, data) { $timeout(function() {

            // The function finaleapi.transposeTable converts a column major table in Finale API's usual format to an array of objects
            // For example, it converts {a:[1,2], b:[4,null]} to [{a:1,b:4}, {a:2, b:null}]
            // This is wrapped in a bit lodash code to index the objects by their primary key field, which in this example is always the first field
            v.data = _(finaleapi.transposeTable(data.rslt)).map(function(cur) { return [cur[v.fields[0]], cur]}).zipObject().value()

            // When all data has loaded, convert the ISO8601 orderDate into a JavaScipt Date object and force the calendar to update
            if (_(results).every(function(v) { return v.data})) {
              _(results.order.data).forEach(function(cur) { cur.orderDate = new Date(cur.orderDate) })
              $scope.calendar.fullCalendar('refetchEvents')
            }
          })})
        })
      })


      // Update events in the calendar from the data fetched from the host

      function formatOrder(order) {
        var type = order.orderTypeId == 'SALES_ORDER' ? {title:'Sale', roleTypeId:'CUSTOMER'} : {title:'Purchase', roleTypeId:'SUPPLIER'},
            party = results.partyGroup.data[(_(order.orderRoleList).find(function(cur) { return cur.roleTypeId == type.roleTypeId }) || {}).partyId]

        return type.title + ': ' + order.orderId + (party ? '\n'+party.groupName : '')
      }

      $scope.events = [{events:function(start, end, cb) {
        cb(_(results.order.data)
          .filter(function(order) { return order.orderDate >= start && order.orderDate <= end })
          .map(function(order) { return {title: formatOrder(order), start: order.orderDate}})
          .value()
      )}}]

      $scope.calendarSettings = { header: {left:'title', right:'month,basicWeek,basicDay today prev,next'}, aspectRatio:2, }
    })

    // Always connect to the host immediately upon being loaded.  Note that when the add-on is installed into Finale the connection must be completed successfully
    .run(function(host) {
    })

}());
