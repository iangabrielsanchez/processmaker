<template>
  <div class="data-table">
    <data-loading
            :for="/screens/"
            v-show="shouldShowLoader"
            :empty="$t('No Data Available')"
            :empty-desc="$t('')"
            empty-icon="noData"
    />
    <div v-show="!shouldShowLoader" class="card card-body table-card" data-cy="screens-table">
      <vuetable
        :dataManager="dataManager"
        :noDataTemplate="$t('No Data Available')"
        :sortOrder="sortOrder"
        :css="css"
        :api-mode="false"
        @vuetable:pagination-data="onPaginationData"
        :fields="fields"
        :data="data"
        data-path="data"
        pagination-path="meta"
      >
        <template slot="title" slot-scope="props">
          <b-link
            @click="onAction('edit-screen', props.rowData, props.rowIndex)"
            v-if="permission.includes('edit-screens')"
          ><span v-uni-id="props.rowData.id.toString()">{{props.rowData.title}}</span></b-link>
          <span v-uni-id="props.rowData.id.toString()" v-else="permission.includes('edit-screens')">{{props.rowData.title}}</span>
        </template>

        <template slot="actions" slot-scope="props">
          <div class="actions">
            <div class="popout">
              <b-btn
                variant="link"
                @click="onAction('edit-screen', props.rowData, props.rowIndex)"
                v-b-tooltip.hover
                :title="$t('Edit')"
                v-if="permission.includes('edit-screens')"
                v-uni-aria-describedby="props.rowData.id.toString()"
              >
                <i class="fas fa-pen-square fa-lg fa-fw"></i>
              </b-btn>
              <b-btn
                variant="link"
                @click="onAction('edit-item', props.rowData, props.rowIndex)"
                v-b-tooltip.hover
                :title="$t('Configure')"
                v-if="permission.includes('edit-screens')"
                v-uni-aria-describedby="props.rowData.id.toString()"
              >
                <i class="fas fa-cog fa-lg fa-fw"></i>
              </b-btn>
              <b-btn
                variant="link"
                @click="onAction('duplicate-item', props.rowData, props.rowIndex)"
                v-b-tooltip.hover
                :title="$t('Copy')"
                v-if="permission.includes('create-screens')"
                v-uni-aria-describedby="props.rowData.id.toString()"
              >
                <i class="fas fa-copy fa-lg fa-fw"></i>
              </b-btn>
              <b-btn
                variant="link"
                @click="onAction('export-item', props.rowData, props.rowIndex)"
                v-b-tooltip.hover
                :title="$t('Export')"
                v-if="permission.includes('export-screens')"
                v-uni-aria-describedby="props.rowData.id.toString()"
              >
                <i class="fas fa-file-export fa-lg fa-fw"></i>
              </b-btn>
              <b-btn
                variant="link"
                @click="onAction('remove-item', props.rowData, props.rowIndex)"
                v-b-tooltip.hover
                :title="$t('Delete')"
                v-if="permission.includes('delete-screens')"
                v-uni-aria-describedby="props.rowData.id.toString()"
              >
                <i class="fas fa-trash-alt fa-lg fa-fw"></i>
              </b-btn>
            </div>
          </div>
        </template>
      </vuetable>
      <pagination
        single="Screen"
        plural="Screens"
        :perPageSelectEnabled="true"
        @changePerPage="changePerPage"
        @vuetable-pagination:change-page="onPageChange"
        ref="pagination"
      ></pagination>
    </div>
    <b-modal ref="myModalRef" :title="$t('Copy Screen')" centered header-close-content="&times;">
      <form>
        <div class="form-group">
          <label for="title">{{$t('Name')}}<small class="ml-1">*</small></label>
          <input id="title"
            type="text"
            class="form-control"
            v-model="dupScreen.title"
            v-bind:class="{ 'is-invalid': errors.title }"
          />
          <div class="invalid-feedback" role="alert" v-if="errors.title">{{errors.title[0]}}</div>
        </div>
        <div class="form-group">
          <label for="type">{{$t('Type')}}</label>
          <select class="form-control" id="type" disabled>
            <option>{{dupScreen.type}}</option>
          </select>
        </div>
        <div class="form-group">
          <category-select
          :label="$t('Category')"
          api-get="screen_categories"
          api-list="screen_categories"
          v-model="dupScreen.screen_category_id"
          :errors="errors.screen_category_id">
          </category-select>
        </div>
        <div class="form-group">
          <label for="description">{{$t('Description')}}</label>
          <textarea class="form-control" id="description" rows="3" v-model="dupScreen.description"></textarea>
        </div>
      </form>
      <div slot="modal-footer" class="w-100" align="right">
        <button type="button" class="btn btn-outline-secondary" @click="hideModal">{{$t('Cancel')}}</button>
        <button type="button" @click="onSubmit" class="btn btn-secondary ml-2">{{$t('Save')}}</button>
      </div>
    </b-modal>
  </div>
</template>

<script>
import datatableMixin from "../../../components/common/mixins/datatable";
import dataLoadingMixin from "../../../components/common/mixins/apiDataLoading";
import { createUniqIdsMixin } from "vue-uniq-ids";
const uniqIdsMixin = createUniqIdsMixin();

export default {
  mixins: [datatableMixin, dataLoadingMixin, uniqIdsMixin],
  props: ["filter", "id", "permission"],
  data() {
    return {
      orderBy: "title",
      dupScreen: {
        title: "",
        type: "",
        category: {},
        screen_category_id: "",
        description: ""
      },
      errors: [],
      sortOrder: [
        {
          field: "title",
          sortField: "title",
          direction: "asc"
        }
      ],

      fields: [
        {
          title: () => this.$t("Name"),
          name: "__slot:title",
          field: "title",
          sortField: "title"
        },
        {
          title: () => this.$t("Description"),
          name: "description",
          sortField: "description"
        },
        {
          title: () => this.$t("Category"),
          name: "categories",
          sortField: "category.name",
          callback(categories) {
            return categories.map(item => item.name).join(', ');
          }
        },
        {
          title: () => this.$t("Type"),
          name: "type",
          sortField: "type",
          callback: this.formatType
        },
        {
          title: () => this.$t("Modified"),
          name: "updated_at",
          sortField: "updated_at",
          callback: "formatDate"
        },
        {
          title: () => this.$t("Created"),
          name: "created_at",
          sortField: "created_at",
          callback: "formatDate"
        },
        {
          name: "__slot:actions",
          title: ""
        }
      ]
    };
  },

  methods: {
    formatType(type) {
      return this.$t(_.startCase(_.toLower(type)));
    },
    showModal() {
      this.$refs.myModalRef.show();
    },
    hideModal() {
      this.$refs.myModalRef.hide();
    },
    onSubmit() {
      ProcessMaker.apiClient
        .put("screens/" + this.dupScreen.id + "/duplicate", this.dupScreen)
        .then(response => {
          ProcessMaker.alert(this.$t("The screen was duplicated."), "success");
          this.hideModal();
          this.fetch();
        })
        .catch(error => {
          if (error.response.status && error.response.status === 422) {
            this.errors = error.response.data.errors;
          }
        });
    },
    onAction(actionType, data, index) {
      switch (actionType) {
        case "edit-screen":
          window.location.href =
            "/designer/screen-builder/" + data.id + "/edit";
          break;
        case "edit-item":
          window.location.href = "/designer/screens/" + data.id + "/edit";
          break;
        case "duplicate-item":
          this.dupScreen.title = data.title + ' ' + this.$t('Copy');
          this.dupScreen.type = data.type;
          this.dupScreen.category = data.category;
          this.dupScreen.screen_category_id = data.screen_category_id;
          this.dupScreen.description = data.description;
          this.dupScreen.id = data.id;
          this.showModal();
          break;
        case "export-item":
          window.location.href = "/designer/screens/" + data.id + "/export";
          break;
        case "remove-item":
          let that = this;
          ProcessMaker.confirmModal(
            this.$t("Caution!"),
             this.$t("Are you sure you want to delete the screen {{item}}? Deleting this asset will break any active tasks that are assigned.", {
                item: data.title,
              }),
              "",
            function() {
              ProcessMaker.apiClient
                .delete("screens/" + data.id)
                .then(response => {
                  ProcessMaker.alert(
                    this.$t("The screen was deleted."),
                    "success"
                  );
                  that.fetch();
                });
            }
          );
          break;
      }
    },
    fetch() {
      this.loading = true;
      //change method sort by slot name
      this.orderBy = this.orderBy === "__slot:title" ? "title" : this.orderBy;
      // Load from our api client
      ProcessMaker.apiClient
        .get(
          "screens" +
            "?page=" +
            this.page +
            "&per_page=" +
            this.perPage +
            "&filter=" +
            this.filter +
            "&order_by=" +
            this.orderBy +
            "&order_direction=" +
            this.orderDirection +
            "&include=categories,category" +
            "&exclude=config"
    )
        .then(response => {
          this.data = this.transform(response.data);
          this.loading = false;
        });
    },
  },

  computed: {}
};
</script>

<style lang="scss" scoped>
:deep(th#_total_users) {
  width: 150px;
  text-align: center;
}

:deep(th#_description) {
  width: 250px;
}

:deep(.rounded-user) {
  border-radius: 50% !important;
  height: 1.5em;
  margin-right: 0.5em;
}
</style>
