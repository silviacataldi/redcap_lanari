((typeof self !== 'undefined' ? self : this)["webpackJsonpfhir_stats_vue"] = (typeof self !== 'undefined' ? self : this)["webpackJsonpfhir_stats_vue"] || []).push([[3],{

/***/ "bc13":
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
// ESM COMPAT FLAG
__webpack_require__.r(__webpack_exports__);

// CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js?{"cacheDirectory":"node_modules/.cache/vue-loader","cacheIdentifier":"9c6b62ca-vue-loader-template"}!./node_modules/vue-loader/lib/loaders/templateLoader.js??vue-loader-options!./node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/vue-loader/lib??vue-loader-options!./src/pages/Home.vue?vue&type=template&id=3ece3310&scoped=true&
var render = function () {var _vm=this;var _h=_vm.$createElement;var _c=_vm._self._c||_h;return _c('div',{staticClass:"page-wrapper"},[_c('SearchForm'),_c('Charts',{staticClass:"mt-2"})],1)}
var staticRenderFns = []


// CONCATENATED MODULE: ./src/pages/Home.vue?vue&type=template&id=3ece3310&scoped=true&

// CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js?{"cacheDirectory":"node_modules/.cache/vue-loader","cacheIdentifier":"9c6b62ca-vue-loader-template"}!./node_modules/vue-loader/lib/loaders/templateLoader.js??vue-loader-options!./node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/vue-loader/lib??vue-loader-options!./src/components/SearchForm.vue?vue&type=template&id=66d74faf&
var SearchFormvue_type_template_id_66d74faf_render = function () {var _vm=this;var _h=_vm.$createElement;var _c=_vm._self._c||_h;return _c('div',[_c('b-form',{on:{"submit":_vm.onSubmit,"reset":_vm.onReset}},[_c('div',{staticClass:"d-flex mb-2"},[_c('b-form-datepicker',{attrs:{"placeholder":"start date","max":_vm.toProxy || _vm.today,"today-button":"","reset-button":"","close-button":""},model:{value:(_vm.fromProxy),callback:function ($$v) {_vm.fromProxy=$$v},expression:"fromProxy"}}),_c('b-form-datepicker',{staticClass:"ms-2",attrs:{"placeholder":"end date","min":_vm.fromProxy,"max":_vm.today,"today-button":"","reset-button":"","close-button":""},model:{value:(_vm.toProxy),callback:function ($$v) {_vm.toProxy=$$v},expression:"toProxy"}})],1),_c('b-button',{attrs:{"type":"submit","variant":"primary","disabled":_vm.loading}},[(_vm.loading)?_c('font-awesome-icon',{attrs:{"icon":['fas', 'spinner'],"spin":"","fixed-width":""}}):_c('font-awesome-icon',{attrs:{"icon":['fas', 'chart-bar'],"fixed-width":""}}),_c('span',{staticClass:"ms-2"},[_vm._v("Display results")])],1),_c('b-button',{staticClass:"ms-2",attrs:{"type":"reset","variant":"danger"}},[_c('font-awesome-icon',{attrs:{"icon":['fas', 'undo'],"fixed-width":""}}),_c('span',{staticClass:"ms-2"},[_vm._v("Reset")])],1)],1)],1)}
var SearchFormvue_type_template_id_66d74faf_staticRenderFns = []


// CONCATENATED MODULE: ./src/components/SearchForm.vue?vue&type=template&id=66d74faf&

// EXTERNAL MODULE: ./node_modules/@babel/runtime/helpers/esm/asyncToGenerator.js
var asyncToGenerator = __webpack_require__("1da1");

// EXTERNAL MODULE: ./node_modules/regenerator-runtime/runtime.js
var runtime = __webpack_require__("96cf");

// CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js??ref--12-0!./node_modules/thread-loader/dist/cjs.js!./node_modules/babel-loader/lib!./node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/vue-loader/lib??vue-loader-options!./src/components/SearchForm.vue?vue&type=script&lang=js&


//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
/* harmony default export */ var SearchFormvue_type_script_lang_js_ = ({
  data: function data() {
    var today = new Date();
    return {
      loading: false,
      today: today
    };
  },
  computed: {
    queryParams: function queryParams() {
      return {
        date_start: this.fromProxy,
        date_end: this.toProxy
      };
    },
    fromProxy: {
      get: function get() {
        return this.$route.query.date_start;
      },
      set: function set(value) {
        var query = this.queryParams;
        query.date_start = value;
        this.$router.push({
          query: query
        });
      }
    },
    toProxy: {
      get: function get() {
        return this.$route.query.date_end;
      },
      set: function set(value) {
        var query = this.queryParams;
        query.date_end = value;
        this.$router.push({
          query: query
        });
      }
    }
  },
  methods: {
    onSubmit: function onSubmit(event) {
      var _this = this;

      return Object(asyncToGenerator["a" /* default */])( /*#__PURE__*/regeneratorRuntime.mark(function _callee() {
        return regeneratorRuntime.wrap(function _callee$(_context) {
          while (1) {
            switch (_context.prev = _context.next) {
              case 0:
                event.preventDefault();
                _this.loading = true;
                _context.next = 4;
                return _this.$store.dispatch('stats/getStats', {
                  from: _this.fromProxy,
                  to: _this.toProxy,
                  vm: _this
                });

              case 4:
                _this.loading = false;

              case 5:
              case "end":
                return _context.stop();
            }
          }
        }, _callee);
      }))();
    },
    onReset: function onReset() {
      this.fromProxy = this.toProxy = null;
    }
  }
});
// CONCATENATED MODULE: ./src/components/SearchForm.vue?vue&type=script&lang=js&
 /* harmony default export */ var components_SearchFormvue_type_script_lang_js_ = (SearchFormvue_type_script_lang_js_); 
// EXTERNAL MODULE: ./node_modules/vue-loader/lib/runtime/componentNormalizer.js
var componentNormalizer = __webpack_require__("2877");

// CONCATENATED MODULE: ./src/components/SearchForm.vue





/* normalize component */

var component = Object(componentNormalizer["a" /* default */])(
  components_SearchFormvue_type_script_lang_js_,
  SearchFormvue_type_template_id_66d74faf_render,
  SearchFormvue_type_template_id_66d74faf_staticRenderFns,
  false,
  null,
  null,
  null
  
)

/* harmony default export */ var SearchForm = (component.exports);
// CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js?{"cacheDirectory":"node_modules/.cache/vue-loader","cacheIdentifier":"9c6b62ca-vue-loader-template"}!./node_modules/vue-loader/lib/loaders/templateLoader.js??vue-loader-options!./node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/vue-loader/lib??vue-loader-options!./src/components/Charts.vue?vue&type=template&id=e6f4ce26&
var Chartsvue_type_template_id_e6f4ce26_render = function () {var _vm=this;var _h=_vm.$createElement;var _c=_vm._self._c||_h;return _c('div',{directives:[{name:"show",rawName:"v-show",value:(_vm.loaded),expression:"loaded"}]},[_c('b-tabs',{attrs:{"content-class":"mt-3"},scopedSlots:_vm._u([{key:"tabs-end",fn:function(){return [_c('div',{staticClass:"ml-auto"})]},proxy:true}])},[_c('b-tab',{attrs:{"title":"Overall","active":""}},[_c('TotalChart',{attrs:{"chartdata":_vm.overall_total}}),_c('DailyChart',{attrs:{"chartdata":_vm.overall_daily}})],1),_c('b-tab',{attrs:{"title":"Clinical Data Mart"}},[_c('TotalChart',{attrs:{"chartdata":_vm.CDM_total}}),_c('DailyChart',{attrs:{"chartdata":_vm.CDM_daily}})],1),_c('b-tab',{attrs:{"title":"Clinical Data Pull"}},[_c('TotalChart',{attrs:{"chartdata":_vm.CDP_total}}),_c('DailyChart',{attrs:{"chartdata":_vm.CDP_daily}})],1),_c('b-tab',{attrs:{"title":"Clinical Data Pull (instant adjudication)"}},[_c('TotalChart',{attrs:{"chartdata":_vm.CDP_I_total}}),_c('DailyChart',{attrs:{"chartdata":_vm.CDP_I_daily}})],1)],1),_c('b-button',{staticClass:"mt-2 text-white",attrs:{"size":"sm","href":_vm.metadata.export_link,"download":""}},[(_vm.loading)?_c('font-awesome-icon',{attrs:{"icon":['fas', 'spinner'],"spin":"","fixed-width":""}}):_c('font-awesome-icon',{attrs:{"icon":['fas', 'file-archive'],"fixed-width":""}}),_c('span',{staticClass:"ms-2"},[_vm._v("Export CSV")])],1)],1)}
var Chartsvue_type_template_id_e6f4ce26_staticRenderFns = []


// CONCATENATED MODULE: ./src/components/Charts.vue?vue&type=template&id=e6f4ce26&

// CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js?{"cacheDirectory":"node_modules/.cache/vue-loader","cacheIdentifier":"9c6b62ca-vue-loader-template"}!./node_modules/vue-loader/lib/loaders/templateLoader.js??vue-loader-options!./node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/vue-loader/lib??vue-loader-options!./src/components/charts/TotalChart.vue?vue&type=template&id=e8a5c2e8&
var TotalChartvue_type_template_id_e8a5c2e8_render = function () {var _vm=this;var _h=_vm.$createElement;var _c=_vm._self._c||_h;return _c('div',[_c('canvas',{ref:"chart"})])}
var TotalChartvue_type_template_id_e8a5c2e8_staticRenderFns = []


// CONCATENATED MODULE: ./src/components/charts/TotalChart.vue?vue&type=template&id=e8a5c2e8&

// EXTERNAL MODULE: ./node_modules/@babel/runtime/helpers/esm/toConsumableArray.js + 3 modules
var toConsumableArray = __webpack_require__("2909");

// EXTERNAL MODULE: ./node_modules/chart.js/dist/chart.esm.js + 1 modules
var chart_esm = __webpack_require__("9b4a");

// CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js??ref--12-0!./node_modules/thread-loader/dist/cjs.js!./node_modules/babel-loader/lib!./node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/vue-loader/lib??vue-loader-options!./src/components/charts/TotalChart.vue?vue&type=script&lang=js&

//
//
//
//
//
//
//

chart_esm["a" /* Chart */].register.apply(chart_esm["a" /* Chart */], Object(toConsumableArray["a" /* default */])(chart_esm["b" /* registerables */]));
/* harmony default export */ var TotalChartvue_type_script_lang_js_ = ({
  data: function data() {
    return {
      chart: null // register the canvas context. needed to destroy before update

    };
  },
  computed: {},
  methods: {
    makeChart: function makeChart() {
      var ctx = this.$refs['chart'];
      if (!ctx) return;
      if (this.chart) this.chart.destroy();
      this.chart = new chart_esm["a" /* Chart */](ctx, {
        type: 'bar',
        data: this.chartdata,
        options: {
          responsive: true,
          plugins: {
            title: {
              display: true,
              text: 'Total entries' // Entries per day

            }
          },
          interaction: {
            intersect: false,
            mode: 'dataset'
          },
          x: {
            title: {
              display: true,
              text: 'Day'
            }
          },
          y: {
            title: {
              display: true,
              text: 'Entries'
            }
            /* min: 0,
            max: 100,
            ticks: {
                // forces step size to be 50 units
                stepSize: 50
            } */

          }
        }
      });
      return this.chart;
    }
  },
  props: {
    chartdata: {
      type: Object,
      default: null
    },
    options: {
      type: Object,
      default: function _default() {
        return {
          responsive: true,
          maintainAspectRatio: false
        };
      }
    }
  },
  watch: {
    chartdata: {
      immediate: true,
      deep: true,
      handler: function handler() {
        this.makeChart();
      }
    }
  }
});
// CONCATENATED MODULE: ./src/components/charts/TotalChart.vue?vue&type=script&lang=js&
 /* harmony default export */ var charts_TotalChartvue_type_script_lang_js_ = (TotalChartvue_type_script_lang_js_); 
// CONCATENATED MODULE: ./src/components/charts/TotalChart.vue





/* normalize component */

var TotalChart_component = Object(componentNormalizer["a" /* default */])(
  charts_TotalChartvue_type_script_lang_js_,
  TotalChartvue_type_template_id_e8a5c2e8_render,
  TotalChartvue_type_template_id_e8a5c2e8_staticRenderFns,
  false,
  null,
  null,
  null
  
)

/* harmony default export */ var TotalChart = (TotalChart_component.exports);
// CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js?{"cacheDirectory":"node_modules/.cache/vue-loader","cacheIdentifier":"9c6b62ca-vue-loader-template"}!./node_modules/vue-loader/lib/loaders/templateLoader.js??vue-loader-options!./node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/vue-loader/lib??vue-loader-options!./src/components/charts/DailyChart.vue?vue&type=template&id=770ab1b2&
var DailyChartvue_type_template_id_770ab1b2_render = function () {var _vm=this;var _h=_vm.$createElement;var _c=_vm._self._c||_h;return _c('div',[_c('canvas',{ref:"chart"})])}
var DailyChartvue_type_template_id_770ab1b2_staticRenderFns = []


// CONCATENATED MODULE: ./src/components/charts/DailyChart.vue?vue&type=template&id=770ab1b2&

// EXTERNAL MODULE: ./node_modules/@babel/runtime/helpers/esm/objectSpread2.js
var objectSpread2 = __webpack_require__("5530");

// EXTERNAL MODULE: ./node_modules/core-js/modules/es.array.map.js
var es_array_map = __webpack_require__("d81d");

// CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js??ref--12-0!./node_modules/thread-loader/dist/cjs.js!./node_modules/babel-loader/lib!./node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/vue-loader/lib??vue-loader-options!./src/components/charts/DailyChart.vue?vue&type=script&lang=js&



//
//
//
//
//
//
//

chart_esm["a" /* Chart */].register.apply(chart_esm["a" /* Chart */], Object(toConsumableArray["a" /* default */])(chart_esm["b" /* registerables */]));
/* harmony default export */ var DailyChartvue_type_script_lang_js_ = ({
  data: function data() {
    return {
      chart: null // register the canvas context. needed to destroy before update

    };
  },
  computed: {
    data: function data() {
      var datasetConfig = {
        borderWidth: 1,
        fill: false,
        cubicInterpolationMode: 'monotone',
        tension: 0.4
      };

      var _this$chartdata = Object(objectSpread2["a" /* default */])({}, this.chartdata),
          labels = _this$chartdata.labels,
          datasets = _this$chartdata.datasets;

      datasets = datasets.map(function (dataset) {
        return Object(objectSpread2["a" /* default */])(Object(objectSpread2["a" /* default */])({}, datasetConfig), dataset);
      });
      return {
        labels: labels,
        datasets: datasets
      };
    }
  },
  methods: {
    makeChart: function makeChart() {
      var ctx = this.$refs['chart'];
      if (!ctx) return;
      if (this.chart) this.chart.destroy();
      this.chart = new chart_esm["a" /* Chart */](ctx, {
        type: 'line',
        data: this.data,
        options: {
          responsive: true,
          plugins: {
            title: {
              display: true,
              text: 'Entries per day' // Entries per day

            }
          },
          interaction: {
            intersect: false,
            mode: 'x'
          },
          scales: {
            x: {
              title: {
                display: true,
                text: 'Day'
              }
            },
            y: {
              title: {
                display: true,
                text: 'Entries'
              }
            }
          }
        }
      });
      return this.chart;
    }
  },
  props: {
    chartdata: {
      type: Object,
      default: null
    },
    options: {
      type: Object,
      default: function _default() {
        return {
          responsive: true,
          maintainAspectRatio: false
        };
      }
    }
  },
  watch: {
    chartdata: {
      immediate: true,
      deep: true,
      handler: function handler() {
        this.makeChart();
      }
    }
  }
});
// CONCATENATED MODULE: ./src/components/charts/DailyChart.vue?vue&type=script&lang=js&
 /* harmony default export */ var charts_DailyChartvue_type_script_lang_js_ = (DailyChartvue_type_script_lang_js_); 
// CONCATENATED MODULE: ./src/components/charts/DailyChart.vue





/* normalize component */

var DailyChart_component = Object(componentNormalizer["a" /* default */])(
  charts_DailyChartvue_type_script_lang_js_,
  DailyChartvue_type_template_id_770ab1b2_render,
  DailyChartvue_type_template_id_770ab1b2_staticRenderFns,
  false,
  null,
  null,
  null
  
)

/* harmony default export */ var DailyChart = (DailyChart_component.exports);
// EXTERNAL MODULE: ./node_modules/@babel/runtime/helpers/esm/slicedToArray.js + 3 modules
var slicedToArray = __webpack_require__("3835");

// EXTERNAL MODULE: ./node_modules/@babel/runtime/helpers/esm/defineProperty.js
var defineProperty = __webpack_require__("ade3");

// EXTERNAL MODULE: ./node_modules/@babel/runtime/helpers/esm/classCallCheck.js
var classCallCheck = __webpack_require__("d4ec");

// EXTERNAL MODULE: ./node_modules/@babel/runtime/helpers/esm/createClass.js
var createClass = __webpack_require__("bee2");

// EXTERNAL MODULE: ./node_modules/core-js/modules/es.array.concat.js
var es_array_concat = __webpack_require__("99af");

// EXTERNAL MODULE: ./node_modules/core-js/modules/es.array.join.js
var es_array_join = __webpack_require__("a15b");

// EXTERNAL MODULE: ./node_modules/core-js/modules/es.object.values.js
var es_object_values = __webpack_require__("07ac");

// EXTERNAL MODULE: ./node_modules/core-js/modules/es.object.keys.js
var es_object_keys = __webpack_require__("b64b");

// EXTERNAL MODULE: ./node_modules/core-js/modules/es.object.entries.js
var es_object_entries = __webpack_require__("4fad");

// CONCATENATED MODULE: ./src/libs/FhirDataChartAdapter.js










var COLORS = {
  red_alt1: [255, 99, 132],
  blue_alt1: [54, 162, 235],
  yellow_alt1: [255, 206, 86],
  green_alt1: [75, 192, 192],
  purple_alt1: [153, 102, 255],
  orange_alt1: [255, 159, 64],
  teal_alt1: [32, 201, 151],
  cyan_alt1: [23, 162, 184],
  // 'pure' colors from here
  lime: [0, 255, 0],
  aqua: [0, 255, 255],
  maroon: [128, 0, 0],
  navy: [0, 0, 128],
  olive: [128, 128, 0],
  silver: [192, 192, 192],
  blue: [0, 0, 255],
  fuchsia: [255, 0, 255],
  green: [0, 128, 0],
  teal: [0, 128, 128],
  purple: [128, 0, 128],
  gray: [128, 128, 128],
  red: [255, 0, 0],
  yellow: [255, 255, 0],
  black: [0, 0, 0],
  white: [255, 255, 255]
};
/**
 * class that generates CSS valid rgba colors
 */

var FhirDataChartAdapter_ColorMaker = /*#__PURE__*/function () {
  function ColorMaker() {
    Object(classCallCheck["a" /* default */])(this, ColorMaker);
  }

  Object(createClass["a" /* default */])(ColorMaker, [{
    key: "makeCssColor",
    value:
    /**
     * helper method to create a list of CSS color strings
     * with custom opacity
     */
    function makeCssColor(color) {
      var opacity = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 1;
      opacity = parseFloat(opacity);
      return "rgba(".concat(color.join(', '), ", ").concat(opacity, ")");
    }
  }, {
    key: "makeCssColors",
    value: function makeCssColors(opacity) {
      var _this = this;

      // curried function will be used in map
      var makeCssColor = function makeCssColor(color) {
        return _this.makeCssColor(color, opacity);
      };

      return Object.values(COLORS).map(makeCssColor);
    }
  }, {
    key: "getRandomColor",
    value: function getRandomColor(value) {
      var opacity = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 0.2;
      opacity = parseFloat(opacity); //value from 0 to 1

      value += 1;
      var max = 200;
      var red = Math.round(value * Math.random() * 200) % max;
      var green = Math.round(value * Math.random() * 200) % max;
      var blue = Math.round(value * Math.random() * 200) % max;
      var colors = [red, green, blue, opacity].join(', ');
      return 'rgba(' + colors + ')'; // hlsa version
      // var hue=((value+200)*20).toString(10)
      // return ["hsla(",hue,",100%,50%, .2)"].join("")
    }
  }]);

  return ColorMaker;
}();

var FhirDataChartAdapter_FhirDataChartAdapter = function FhirDataChartAdapter() {
  var _this2 = this;

  Object(classCallCheck["a" /* default */])(this, FhirDataChartAdapter);

  Object(defineProperty["a" /* default */])(this, "makeTotalChartData", function (data) {
    var label = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : '';
    var chartData = {
      labels: Object.keys(data),
      datasets: [{
        data: data,
        label: label,
        backgroundColor: _this2.colorMaker.makeCssColors(0.2),
        borderColor: _this2.colorMaker.makeCssColors(1),
        borderWidth: 1
      }]
    };
    return chartData;
  });

  Object(defineProperty["a" /* default */])(this, "makeDailyChartData", function (dailyData) {
    var groupDataByResource = function groupDataByResource(dailyData) {
      var datasets = {};

      for (var _i = 0, _Object$values = Object.values(dailyData); _i < _Object$values.length; _i++) {
        var data = _Object$values[_i];

        for (var _i2 = 0, _Object$entries = Object.entries(data); _i2 < _Object$entries.length; _i2++) {
          var _Object$entries$_i = Object(slicedToArray["a" /* default */])(_Object$entries[_i2], 2),
              type = _Object$entries$_i[0],
              counts = _Object$entries$_i[1];

          if (!datasets[type]) datasets[type] = [];
          datasets[type].push(counts);
        }
      }

      return datasets;
    };

    var dataByResource = groupDataByResource(dailyData);
    var labels = Object.keys(dailyData);
    var datasets = [];
    var counter = 0; // use this index to pick a color

    var backgroundColors = _this2.colorMaker.makeCssColors(0.2);

    var borderColors = _this2.colorMaker.makeCssColors(1);

    for (var _i3 = 0, _Object$entries2 = Object.entries(dataByResource); _i3 < _Object$entries2.length; _i3++) {
      var _Object$entries2$_i = Object(slicedToArray["a" /* default */])(_Object$entries2[_i3], 2),
          type = _Object$entries2$_i[0],
          data = _Object$entries2$_i[1];

      var colorIndex = counter++;
      var dataset = {
        label: type,
        backgroundColor: backgroundColors[colorIndex],
        borderColor: borderColors[colorIndex],
        fill: false,
        data: data //set the data

      };
      datasets.push(dataset);
    }

    var chartData = {
      labels: labels,
      datasets: datasets
    };
    return chartData;
  });

  this.colorMaker = new FhirDataChartAdapter_ColorMaker();
}
/**
 * format a 'total' type of data
 * received from the API.
 * 
 */
;


// CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js??ref--12-0!./node_modules/thread-loader/dist/cjs.js!./node_modules/babel-loader/lib!./node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/vue-loader/lib??vue-loader-options!./src/components/Charts.vue?vue&type=script&lang=js&
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//



var adapter = new FhirDataChartAdapter_FhirDataChartAdapter();
/* harmony default export */ var Chartsvue_type_script_lang_js_ = ({
  components: {
    TotalChart: TotalChart,
    DailyChart: DailyChart
  },
  data: function data() {
    return {
      loading: false
    };
  },
  computed: {
    loaded: function loaded() {
      return this.$store.state.stats.loaded;
    },
    overall_total: function overall_total() {
      return adapter.makeTotalChartData(this.$store.state.stats.overall_total, 'Overall total');
    },
    CDM_total: function CDM_total() {
      return adapter.makeTotalChartData(this.$store.state.stats.CDM_total, 'CDM total');
    },
    CDP_total: function CDP_total() {
      return adapter.makeTotalChartData(this.$store.state.stats.CDP_total, 'CDP total');
    },
    CDP_I_total: function CDP_I_total() {
      return adapter.makeTotalChartData(this.$store.state.stats.CDP_I_total, 'CDP instant adjudication total');
    },
    overall_daily: function overall_daily() {
      return adapter.makeDailyChartData(this.$store.state.stats.overall_daily);
    },
    CDM_daily: function CDM_daily() {
      return adapter.makeDailyChartData(this.$store.state.stats.CDM_daily);
    },
    CDP_daily: function CDP_daily() {
      return adapter.makeDailyChartData(this.$store.state.stats.CDP_daily);
    },
    CDP_I_daily: function CDP_I_daily() {
      return adapter.makeDailyChartData(this.$store.state.stats.CDP_I_daily);
    },
    cdm_users_count: function cdm_users_count() {
      return this.$store.state.stats.cdm_users_count;
    },
    date_from: function date_from() {
      return this.$store.state.stats.date_from;
    },
    date_to: function date_to() {
      return this.$store.state.stats.date_to;
    },
    metadata: function metadata() {
      return this.$store.state.stats.metadata;
    }
  },
  methods: {}
});
// CONCATENATED MODULE: ./src/components/Charts.vue?vue&type=script&lang=js&
 /* harmony default export */ var components_Chartsvue_type_script_lang_js_ = (Chartsvue_type_script_lang_js_); 
// CONCATENATED MODULE: ./src/components/Charts.vue





/* normalize component */

var Charts_component = Object(componentNormalizer["a" /* default */])(
  components_Chartsvue_type_script_lang_js_,
  Chartsvue_type_template_id_e6f4ce26_render,
  Chartsvue_type_template_id_e6f4ce26_staticRenderFns,
  false,
  null,
  null,
  null
  
)

/* harmony default export */ var Charts = (Charts_component.exports);
// CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js??ref--12-0!./node_modules/thread-loader/dist/cjs.js!./node_modules/babel-loader/lib!./node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/vue-loader/lib??vue-loader-options!./src/pages/Home.vue?vue&type=script&lang=js&
//
//
//
//
//
//
//
//
//


/* harmony default export */ var Homevue_type_script_lang_js_ = ({
  name: 'HomePage',
  components: {
    SearchForm: SearchForm,
    Charts: Charts
  },
  methods: {},
  computed: {}
});
// CONCATENATED MODULE: ./src/pages/Home.vue?vue&type=script&lang=js&
 /* harmony default export */ var pages_Homevue_type_script_lang_js_ = (Homevue_type_script_lang_js_); 
// CONCATENATED MODULE: ./src/pages/Home.vue





/* normalize component */

var Home_component = Object(componentNormalizer["a" /* default */])(
  pages_Homevue_type_script_lang_js_,
  render,
  staticRenderFns,
  false,
  null,
  "3ece3310",
  null
  
)

/* harmony default export */ var Home = __webpack_exports__["default"] = (Home_component.exports);

/***/ })

}]);