;(function() {

  // Helper to convert from the tabular format used by Finale's API to an object mapping from the primary key of the collection to an object per instance:
  // 
  // tableToObjects(['orderUrl','orderId','orderTypeId'], {orderUrl:['/o/892','/o/893'], orderId:['892','ABC'], orderTypeId:['PURCHASE_ORDER','SALES_ORDER']})
  //
  // --->
  // 
  // {"/o/892":{orderUrl:"/o/892",orderId:"892",orderTypeId:"PURCHASE_ORDER"},"/o/893":{orderUrl:"/o/893",orderId:"ABC",orderTypeId:"SALES_ORDER"}} 
  //
  function tableToObjects(fields, table) {
    return _.zipObject(table[fields[0]], _.zip.apply(_, _(fields).map(function(field) { return table[field] }).value()).map(function(values) { return _.zipObject(fields, values) }))
  }

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
            v.data = tableToObjects(v.fields, data.rslt)

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
