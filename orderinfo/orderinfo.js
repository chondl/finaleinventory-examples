;(function() {

  // Couple of constants determining the keys under which data for this add-on will be stored
  var serviceName = 'integration_example_orderinfo',
      attrNameContentUrl = serviceName + '_contentUrl'


  // Accessor functions for a reference to the contentUrl for an order.  The contentUrl is stored in the order's userFieldDataList
  function getOrderContentUrl(order) {
    return (_(order.userFieldDataList).find(function(cur) { return cur.attrName == attrNameContentUrl }) || {}).attrValue
  }

  function setOrderContentUrl(order, contentUrl) {
    // when setting we need to be careful to only change our entry in the userFieldDataList
    order.userFieldDataList = _(order.userFieldDataList || [])
      .filter(function(cur) { return cur.attrName != attrNameContentUrl })
      .push({attrName:attrNameContentUrl, attrValue:contentUrl})
      .value()
  }


  // Read the content object for the order in the current scope, or create a new content object if the order doesn't already have a content object
  function updateScopeContentForScopeOrder($timeout, $log, $scope, host) {
    var contentUrl = getOrderContentUrl($scope.order)
    if (contentUrl) {
      // If the order already has an associated content object, then fetch it and update the scope when complete
      $log.debug('OrderContentController fetch contentUrl', contentUrl)
      host.resourceGET('content', {fields:['contentUrl', 'objectData'], filters:[{contentUrl:contentUrl}]}, function(err, contentResponse) { $timeout(function() {
        if (err) {
          $log.error('OrderContentController fetch content fail', err)
        } else {
          $log.debug('OrderContentController fetch contentUrl success', contentResponse)
          $scope.content = finaleapi.transposeTable(contentResponse.rslt)[0].objectData.content 
        }
      })})
    } else {
      // If the order does not have an associated content object, then create a new blank one and associate it with the order
      $log.debug('OrderContentController create content')
      host.resourcePOST('content', {contentTypeId:'INTEGRATION', serviceName:serviceName, objectData:{content:{}}}, function(err, contentResponse) { $timeout(function() {

        if (err) {
          $log.error('OrderContentController create content fail', err)
        } else {

          // Now that the contentUrl has been assigned by the server we can update the order to reference the newly created content object
          contentUrl = contentResponse.rslt.contentUrl
          $scope.content = contentResponse.rslt.objectData.content
          setOrderContentUrl($scope.order, contentUrl)

          // updating editable/draft orders (which is equivalent to the order's statusId being ORDER_CREATED) will normally succeed as long as the user has write permissions
          // updating committed orders (which is equivalent to the order's statusId being ORDER_LOCKED) will normally fail with a permission error
          // set the autoUnlockRelock flag to have the server unlock and then relock the order to allow the edit to go through
          // if the user does not have the permission to "perform any action" on the order the attempt to unlock will still fail with a permission error
          // attempting to update a completed or canceled order will always fail with a permission error
          host.resourcePOST('order', 
                            {orderUrl: $scope.order.orderUrl, autoUnlockRelock:true, userFieldDataList:$scope.order.userFieldDataList},
                            function(err, orderRslt) { $timeout(function() { 
                              $log.debug('OrderContentController create content success', contentUrl)
                            })})
        }
      })})
    }
  }

  angular
    .module('OrderInfo', ['ngTable'])

    .factory('host', function() {
      return finaleapi
        .hostConnection({ title:'Order info', description:'Collect additional information on every order.' })
        .addListener('show', function(screen, params) {
          this.scrollHeightWrite(800)
          this.showReadyNotify()
        })
    })

    .controller('OrderContentController', function($timeout, $log, $scope, host) {
      var dirty = false, 
          pendingSave = null,
          pendingPost = false

      updateScopeContentForScopeOrder($timeout, $log, $scope, host)

      // Actually store any new edits to the content object for the order being edited
      function doPostContent() {
        var body = {contentUrl:getOrderContentUrl($scope.order), objectData:{content:$scope.content}}
        dirty = false
        pendingPost = true
        $log.debug('doPostContent', body)
        host.resourcePOST('content', body, function(err, contentResponse) { $timeout(function() {
          pendingPost = false
          $log.debug('doPostContent result', err, contentResponse)
          if (dirty) $timeout(doPostContent)
        })})
      }

      // Debounced save operation only posts if there are no other pending posts
      function doSave() {
        pendingSave = null
        if (dirty && !pendingPost) doPostContent()
      }

      // Watch for change in the content object from edits, trigger a save operation after there have been no edits 1000ms (debounce)
      $scope.$watch('content', function(newValue, oldValue) {
        if (newValue === oldValue || !oldValue) return
        if (pendingSave) $timeout.cancel(pendingSave)
        pendingSave = $timeout(doSave, 1000)
        dirty = true
      }, true)
    })

    .controller('OrderListController', function($timeout, $scope, host, ngTableParams) {
      var results = {
            facility: {fields:['facilityUrl','facilityName']},
            partyGroup: {fields:['partyId','groupName']},
            order: {fields:['orderUrl','orderId','orderTypeId','statusId', 'orderDate','orderRoleList', 'userFieldDataList']}
          },
          tableData = []

      $scope.tableParams = new ngTableParams({
        page: 1,
        count: 10,
      }, {
        counts: [],
        total: (results.order.data || []).length,
        getData: function($defer, params) {
          if (tableData) {
            params.total(tableData.length)
            $defer.resolve(tableData.slice((params.page() - 1) * params.count(), params.page() * params.count()))
          } else {
            $defer.resolve([])
          }
        }
      })

      // toggle edit flag on specifed order, and clear edit flag for all other orders
      $scope.edit = function(order) {
        _(tableData).forEach(function(cur) { cur.$edit = cur === order && !order.$edit })
      }

      host.addListener('show', function() {
        _(results).forEach(function(v,k) {
          host.resourceGET(k, {fields:v.fields}, function(err, data) { $timeout(function() {

            v.data = finaleapi.transposeTable(data.rslt)
            v.dataIndexed = _(v.data).map(function(cur) { return [cur[v.fields[0]], cur]}).zipObject().value()

            // once all the data has loaded format the data actually displayed by the table
            if (_(results).every(function(v) { return !!v.data})) {
              tableData = _(results.order.data)
                .filter(function(cur) {
                  return cur.orderTypeId == 'SALES_ORDER' && (cur.statusId == 'ORDER_LOCKED' || cur.statusId == 'ORDER_CREATED') 
                })
                .sortBy(function(cur) {
                  return cur.orderId
                })
                .map(function(cur) { return {
                  orderUrl: cur.orderUrl,
                  orderId: cur.orderId,
                  orderDate: new Date(cur.orderDate).toString(),
                  customer: (results.partyGroup.dataIndexed[(_(cur.orderRoleList).find(function(cur) { return cur.roleTypeId == 'CUSTOMER' }) || {}).partyId] || {}).groupName || '',
                  userFieldDataList: cur.userFieldDataList,
                }})
                .value()
              $scope.tableParams.reload() 
            }
          })})
        })
      })
    })

    // invoke the host service which has the side effect of registering the plugin with the finale
    .run(function(host) {
    })

}());
